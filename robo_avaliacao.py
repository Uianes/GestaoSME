import os
import re
import time
import argparse
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException
from webdriver_manager.chrome import ChromeDriverManager


BASE_URL = "https://avaliacaoftdsim.com.br"


# =========================
# Utils
# =========================
def build_driver(download_dir: Path, headless: bool = False) -> webdriver.Chrome:
    options = Options()
    if headless:
        options.add_argument("--headless=new")

    prefs = {
        "download.default_directory": str(download_dir.resolve()),
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": True
    }
    options.add_experimental_option("prefs", prefs)

    options.add_argument("--window-size=1400,900")
    options.add_argument("--disable-gpu")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")

    service = Service(ChromeDriverManager().install())
    driver = webdriver.Chrome(service=service, options=options)
    driver.set_page_load_timeout(60)
    return driver


def wait_ready(driver, timeout=30):
    WebDriverWait(driver, timeout).until(
        lambda d: d.execute_script("return document.readyState") == "complete"
    )


def click_js(driver, element):
    driver.execute_script("arguments[0].scrollIntoView({block:'center'});", element)
    driver.execute_script("arguments[0].click();", element)


def is_platform_screen(driver) -> bool:
    try:
        return driver.execute_script("""
            const t = document.body ? (document.body.innerText || '') : '';
            return t.includes('Selecionar plataforma');
        """)
    except Exception:
        return "Selecionar plataforma" in driver.page_source


# =========================
# Login / Plataforma
# =========================
def login(driver, email, senha):
    driver.get(BASE_URL)

    WebDriverWait(driver, 30).until(EC.presence_of_element_located((By.ID, "re")))
    WebDriverWait(driver, 30).until(EC.presence_of_element_located((By.ID, "pass")))

    u = driver.find_element(By.ID, "re")
    p = driver.find_element(By.ID, "pass")

    u.clear()
    u.send_keys(email)
    p.clear()
    p.send_keys(senha + "\n")

    WebDriverWait(driver, 30).until(
        EC.presence_of_element_located((By.XPATH, "//*[contains(normalize-space(.), 'Selecionar plataforma')]"))
    )


def click_avaliacao_educacional(driver, timeout: int = 30):
    """
    Clica especificamente no botão 'Avançar' do item que contém
    o texto 'Avaliação Educacional' (o 3º item da lista).

    Estratégia:
    1) JS: acha o texto exato e clica no Avançar dentro do mesmo "card/linha"
    2) fallback: clica no 3º botão Avançar (índice 2)
    """
    WebDriverWait(driver, timeout).until(
        EC.presence_of_element_located((By.XPATH, "//*[contains(normalize-space(.), 'Selecionar plataforma')]"))
    )

    # 1) Preferencial: por texto/mesmo container
    result = driver.execute_script(r"""
        const norm = s => (s || '').replace(/\s+/g,' ').trim().toLowerCase();

        // procura um nó cujo texto seja exatamente "Avaliação Educacional"
        const label = Array.from(document.querySelectorAll('*'))
          .find(el => {
            const t = norm(el.textContent);
            return t === 'avaliação educacional' || t === 'avaliacao educacional';
          });

        if (!label) return {ok:false, error:'label_not_found'};

        // sobe até um container razoável
        const container =
          label.closest('tr') ||
          label.closest('li') ||
          label.closest('.row') ||
          label.closest('.card') ||
          label.closest('.panel') ||
          label.closest('div');

        if (!container) return {ok:false, error:'container_not_found'};

        // botão/link "Avançar" dentro desse mesmo container
        const btn = Array.from(container.querySelectorAll('button, a'))
          .find(el => norm(el.textContent) === 'avançar');

        if (!btn) return {ok:false, error:'button_not_found_in_container'};

        btn.scrollIntoView({block:'center'});
        btn.click();
        return {ok:true, mode:'container_match'};
    """)

    if result and result.get("ok"):
        time.sleep(0.6)
        return

    # 2) Fallback: 3º "Avançar"
    fallback = driver.execute_script(r"""
        const norm = s => (s || '').replace(/\s+/g,' ').trim().toLowerCase();
        const btns = Array.from(document.querySelectorAll('button, a'))
          .filter(el => norm(el.textContent) === 'avançar');

        if (btns.length < 3) return {ok:false, error:'less_than_3_avancar', count:btns.length};

        btns[2].scrollIntoView({block:'center'});
        btns[2].click();
        return {ok:true, mode:'fallback_index_2', count:btns.length};
    """)

    if not fallback or not fallback.get("ok"):
        raise RuntimeError(f"Falha ao clicar em Avaliação Educacional. text_try={result} fallback={fallback}")

    time.sleep(0.6)


# =========================
# Painel / Escolas / Turmas
# =========================
def wait_assessment_panel(driver, timeout: int = 30):
    WebDriverWait(driver, timeout).until(
        EC.presence_of_element_located((By.ID, "assessment-list"))
    )


def get_schools(driver, timeout: int = 30):
    toggle = WebDriverWait(driver, timeout).until(
        EC.element_to_be_clickable((By.CSS_SELECTOR, "li#selectedSchool a.dropdown-toggle"))
    )
    click_js(driver, toggle)

    WebDriverWait(driver, timeout).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, "li.school-item a"))
    )

    schools = driver.execute_script("""
        const norm = s => (s||'').replace(/\\s+/g,' ').trim();
        return Array.from(document.querySelectorAll('li.school-item a'))
          .map(a => ({ name: norm(a.innerText), url: a.href }));
    """)

    # fecha dropdown
    driver.execute_script("document.querySelector('.dropdown-backdrop')?.click();")
    time.sleep(0.2)

    if not schools:
        raise RuntimeError("Não encontrei escolas no dropdown.")

    return schools


def go_to_school(driver, school_url: str, timeout: int = 30):
    driver.get(school_url)
    wait_ready(driver, timeout=timeout)
    time.sleep(0.3)

    # Algumas vezes volta para a tela de plataforma
    if is_platform_screen(driver):
        print("ℹ️ Voltou para 'Selecionar plataforma' ao trocar escola. Reentrando em Avaliação Educacional...")
        click_avaliacao_educacional(driver, timeout=timeout)

    wait_assessment_panel(driver, timeout=timeout)


def collect_simulados(driver):
    anchors = driver.find_elements(By.CSS_SELECTOR, "a.link-assessment")
    simulados = []
    for a in anchors:
        href = a.get_attribute("href")
        title = a.text.strip()
        if href:
            simulados.append({"title": title, "url": href})
    return simulados


# =========================
# Exportação (dentro da turma)
# =========================
def click_analise_dos_alunos(driver, timeout: int = 15):
    try:
        el = WebDriverWait(driver, timeout).until(
            EC.element_to_be_clickable((By.XPATH, "//span[contains(@class,'nav-text') and normalize-space(.)='Análise dos alunos']"))
        )
        click_js(driver, el)
        time.sleep(0.6)
    except TimeoutException:
        # às vezes já entra direto na área de análise
        pass


def click_alunos_x_habilidades(driver, timeout: int = 20):
    # às vezes é <a>, às vezes muda para outro elemento, então tentamos 2 caminhos
    for xpath in [
        "//a[normalize-space(.)='Alunos x Habilidades']",
        "//*[self::a or self::button][contains(normalize-space(.), 'Alunos x Habilidades')]",
    ]:
        try:
            el = WebDriverWait(driver, timeout).until(EC.element_to_be_clickable((By.XPATH, xpath)))
            click_js(driver, el)
            time.sleep(0.6)
            return
        except TimeoutException:
            continue
    raise TimeoutException("Não encontrei 'Alunos x Habilidades'.")


def click_exportar(driver, timeout: int = 30):
    """
    Clica no botão Exportar (na plataforma é um <span id="export">Exportar</span>).
    """
    # Prioridade: id fixo
    el = WebDriverWait(driver, timeout).until(
        EC.presence_of_element_located((By.ID, "export"))
    )

    # garante visível e clicável via JS
    driver.execute_script("""
        arguments[0].scrollIntoView({block:'center'});
        arguments[0].click();
    """, el)

    time.sleep(2.0)  # tempo pro download iniciar

def process_simulados(driver, simulados):
    main_tab = driver.current_window_handle

    for i, s in enumerate(simulados, 1):
        print(f"📤 [{i}/{len(simulados)}] Exportando: {s['title']}")

        driver.execute_script("window.open(arguments[0], '_blank');", s["url"])
        WebDriverWait(driver, 10).until(lambda d: len(d.window_handles) >= 2)

        driver.switch_to.window(driver.window_handles[-1])
        wait_ready(driver, timeout=30)
        time.sleep(0.5)

        try:
            click_analise_dos_alunos(driver)
            click_alunos_x_habilidades(driver)
            click_exportar(driver)
        finally:
            driver.close()
            driver.switch_to.window(main_tab)


# =========================
# Main
# =========================
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--headless", action="store_true", help="Rodar sem abrir janela")
    parser.add_argument("--output", default="downloads", help="Pasta para salvar downloads")
    args = parser.parse_args()

    download_dir = Path(args.output)
    download_dir.mkdir(parents=True, exist_ok=True)

    email = os.getenv("AVALIACAO_USER", "")
    senha = os.getenv("AVALIACAO_PASS", "")

    driver = build_driver(download_dir, headless=args.headless)

    try:
        print("🔐 Login...")
        login(driver, email, senha)

        print("➡️ Plataforma: Avaliação Educacional...")
        click_avaliacao_educacional(driver)

        print("⏳ Aguardando painel...")
        wait_assessment_panel(driver)

        print("🏫 Coletando escolas...")
        schools = get_schools(driver)
        print(f"Encontradas {len(schools)} escolas.")

        for idx, school in enumerate(schools, 1):
            print(f"\n=== [{idx}/{len(schools)}] Escola: {school['name']} ===")
            go_to_school(driver, school["url"])

            simulados = collect_simulados(driver)
            print(f"   📋 {len(simulados)} turmas/simulados")
            if simulados:
                process_simulados(driver, simulados)
            else:
                print("   (Nenhuma turma/simulado encontrado)")

    finally:
        driver.quit()


if __name__ == "__main__":
    main()

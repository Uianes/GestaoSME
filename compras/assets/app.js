const state = {
  categories: [],
  items: [],
  cart: new Map(),
  page: 1,
  limit: 48,
  total: 0,
  totalPages: 1,
};

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

document.addEventListener('DOMContentLoaded', () => {
  loadCategories();
  renderInitialState();

  document.getElementById('searchButton').addEventListener('click', searchItems);
  document.getElementById('searchInput').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') searchItems();
  });
  document.getElementById('categorySelect').addEventListener('change', syncSubcategories);
  document.getElementById('pageSizeSelect').addEventListener('change', () => {
    state.limit = Number(document.getElementById('pageSizeSelect').value);
    state.page = 1;
    searchItems();
  });
  document.getElementById('sortSelect').addEventListener('change', () => {
    state.page = 1;
    searchItems();
  });
  document.getElementById('priceFilterSelect').addEventListener('change', () => {
    state.page = 1;
    searchItems();
  });
  document.getElementById('onlyRelatedSwitch').addEventListener('change', () => {
    state.page = 1;
    searchItems();
  });
  document.getElementById('prevPageButton').addEventListener('click', () => {
    if (state.page > 1) {
      state.page -= 1;
      searchItems();
    }
  });
  document.getElementById('nextPageButton').addEventListener('click', () => {
    if (state.page < state.totalPages) {
      state.page += 1;
      searchItems();
    }
  });
  document.getElementById('pdfForm').addEventListener('submit', preparePdfPayload);
  document.getElementById('excelForm').addEventListener('submit', prepareExcelPayload);
});

async function loadCategories() {
  const response = await fetch('api.php?action=categories');
  const payload = await response.json();
  state.categories = payload.data || [];

  const categorySelect = document.getElementById('categorySelect');
  const unique = [...new Set(state.categories.map((row) => row.categoria))].sort();
  for (const category of unique) {
    categorySelect.append(new Option(category, category));
  }
  syncSubcategories();
}

function syncSubcategories() {
  const category = document.getElementById('categorySelect').value;
  const subcategorySelect = document.getElementById('subcategorySelect');
  subcategorySelect.innerHTML = '<option value="">Todas</option>';

  const subcategories = state.categories
    .filter((row) => !category || row.categoria === category)
    .map((row) => row.subcategoria)
    .filter(Boolean);

  for (const subcategory of [...new Set(subcategories)].sort()) {
    subcategorySelect.append(new Option(subcategory, subcategory));
  }
}

async function searchItems() {
  const params = new URLSearchParams({
    action: 'items',
    q: document.getElementById('searchInput').value,
    category: document.getElementById('categorySelect').value,
    subcategory: document.getElementById('subcategorySelect').value,
    limit: String(state.limit),
    page: String(state.page),
    sort: document.getElementById('sortSelect').value,
    only_with_related: document.getElementById('onlyRelatedSwitch').checked ? '1' : '0',
    price_filter: document.getElementById('priceFilterSelect').value,
  });

  setGridLoading();
  const response = await fetch(`api.php?${params.toString()}`);
  const payload = await response.json();
  state.items = payload.data || [];
  state.total = payload.meta?.total || 0;
  state.totalPages = payload.meta?.total_pages || 1;
  state.page = payload.meta?.page || state.page;
  renderItems();
}

function renderInitialState() {
  document.getElementById('itemsGrid').innerHTML = '<tr><td colspan="6" class="empty-row">Use os filtros e clique em Pesquisar para carregar os itens.</td></tr>';
  document.getElementById('resultSummary').textContent = '';
  document.getElementById('pageSummary').textContent = '';
  document.getElementById('prevPageButton').disabled = true;
  document.getElementById('nextPageButton').disabled = true;
}

function setGridLoading() {
  document.getElementById('itemsGrid').innerHTML = '<tr><td colspan="6" class="empty-row">Carregando itens...</td></tr>';
}

function renderItems() {
  const grid = document.getElementById('itemsGrid');
  document.getElementById('resultSummary').textContent = `${state.total} resultados`;
  document.getElementById('pageSummary').textContent = `Página ${state.page} de ${state.totalPages}`;
  document.getElementById('prevPageButton').disabled = state.page <= 1;
  document.getElementById('nextPageButton').disabled = state.page >= state.totalPages;

  if (!state.items.length) {
    grid.innerHTML = '<tr><td colspan="6" class="empty-row">Nenhum item encontrado.</td></tr>';
    return;
  }

  grid.innerHTML = state.items.map((item) => `
    <tr>
      <td class="item-cell">
        <div class="item-id">#${item.id}</div>
        <div class="table-description">${escapeHtml(item.description)}</div>
        <div class="meta">${escapeHtml(item.unit || '')} ${escapeHtml(item.quantity || '')}</div>
      </td>
      <td>
        <span class="tag">${escapeHtml(item.category || 'Sem categoria')}</span>
        <div class="meta mt-1">${escapeHtml(item.subcategory || '')}</div>
      </td>
      <td>
        <div class="price">${escapeHtml(item.approved_value_label || item.estimated_value_label)}</div>
        <div class="meta">Estimado: ${escapeHtml(item.estimated_value_label || '')}</div>
      </td>
      <td>
        <div>${escapeHtml(item.agency || '')}</div>
        <div class="meta">${escapeHtml(item.modality || '')} ${escapeHtml(item.bid_number || '')}/${escapeHtml(item.bid_year || '')}</div>
      </td>
      <td>
        <span class="related-count">${item.related_count || 0}</span>
      </td>
      <td class="action-cell">
        <button class="btn btn-outline-primary btn-sm" type="button" onclick="addToCart(${item.id})">
          Adicionar
        </button>
      </td>
    </tr>
  `).join('');
}

async function addToCart(itemId) {
  if (state.cart.has(itemId)) {
    renderCart();
    return;
  }

  const item = state.items.find((entry) => entry.id === itemId);
  if (!item) return;

  const related = await fetchRelated(itemId);
  state.cart.set(itemId, { item, related, selectedRelated: new Set() });
  renderCart();
}

async function fetchRelated(itemId) {
  const response = await fetch(`api.php?action=related&item_id=${itemId}`);
  const payload = await response.json();
  return payload.data || [];
}

function removeFromCart(itemId) {
  state.cart.delete(itemId);
  renderCart();
}

function toggleRelated(itemId, relatedId, checked) {
  const entry = state.cart.get(itemId);
  if (!entry) return;

  if (checked) {
    entry.selectedRelated.add(relatedId);
  } else {
    entry.selectedRelated.delete(relatedId);
  }

  renderCartSummary();
}

function renderCart() {
  const container = document.getElementById('cartItems');
  if (!state.cart.size) {
    container.innerHTML = '<p class="text-secondary">Nenhum item no carrinho.</p>';
    renderCartSummary();
    return;
  }

  container.innerHTML = [...state.cart.values()].map(({ item, related, selectedRelated }) => `
    <section class="mb-4 pb-3 border-bottom">
      <div class="d-flex justify-content-between gap-3">
        <div>
          <div class="fw-semibold">#${item.id}</div>
          <div>${escapeHtml(item.description)}</div>
          <div class="price mt-1">${escapeHtml(item.approved_value_label || item.estimated_value_label)}</div>
        </div>
        <button class="btn btn-sm btn-outline-danger align-self-start" onclick="removeFromCart(${item.id})" type="button">Remover</button>
      </div>
      <div class="related-box mt-3">
        <div class="fw-semibold mb-2">Itens relacionados</div>
        ${renderRelatedOptions(item.id, related, selectedRelated)}
      </div>
    </section>
  `).join('');

  renderCartSummary();
}

function renderRelatedOptions(itemId, related, selectedRelated) {
  if (!related.length) {
    return '<div class="text-secondary small">Nenhum item relacionado encontrado.</div>';
  }

  return related.map((item) => `
    <label class="related-option d-flex gap-2">
      <input
        class="form-check-input mt-1"
        id="related-${itemId}-${item.id}"
        type="checkbox"
        ${selectedRelated.has(item.id) ? 'checked' : ''}
        onchange="toggleRelated(${itemId}, ${item.id}, this.checked)"
      >
      <span>
        <span class="d-block">${escapeHtml(item.description)}</span>
        <span class="meta">${escapeHtml(item.approved_value_label || item.estimated_value_label)} · score ${item.score?.toFixed(3) ?? '-'}</span>
      </span>
    </label>
  `).join('');
}

function renderCartSummary() {
  const itemCount = state.cart.size;
  let relatedCount = 0;
  for (const entry of state.cart.values()) {
    relatedCount += entry.selectedRelated.size;
  }

  document.getElementById('cartSummary').textContent = `${itemCount} itens, ${relatedCount} relacionados`;
  document.getElementById('pdfButton').disabled = itemCount === 0;
  document.getElementById('excelButton').disabled = itemCount === 0;
}

function preparePdfPayload() {
  document.getElementById('pdfSelection').value = JSON.stringify(currentSelection());
}

function prepareExcelPayload() {
  document.getElementById('excelSelection').value = JSON.stringify(currentSelection());
}

function currentSelection() {
  return [...state.cart.values()].map(({ item, selectedRelated }) => ({
    item_id: item.id,
    related_ids: [...selectedRelated],
  }));
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

<?php
ini_set('memory_limit', '512M');
set_time_limit(300);
date_default_timezone_set('America/Sao_Paulo');

include './db_connection.php';

if (!isset($_POST['locais']) || empty($_POST['locais'])) {
    echo "<script>alert('Nenhuma escola selecionada.'); window.close();</script>";
    exit;
}

$conn = open_connection();
$locals = array_values(array_filter(array_map('intval', $_POST['locais']), function ($value) {
    return $value > 0;
}));
if (empty($locals)) {
    echo "<script>alert('Nenhuma escola selecionada.'); window.close();</script>";
    close_connection($conn);
    exit;
}
$dateHeader = date('d/m/Y H:i:s');
$dateFile = date('d-m-Y_H:i:s');
$title = "Relatorio_{$dateFile}.pdf";

require_once './vendor/autoload.php';

use Dompdf\Dompdf;

try {
    $html = '<html>
      <head>
        <meta charset="utf-8">
        <title>' . $title . '</title>
        <style>
          @page { margin-top: 40px; }
          #page_header {
              position: fixed;
              top: 0;
              left: 0;
              right: 0;
              height: 30px;
              text-align: center;
              border-bottom: 1px solid #000;
              font-size: 10px;
              line-height: 30px;
          }
          body { font-family: Arial, sans-serif; font-size: 12px; margin-top: 40px; }
          table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
            table-layout: fixed; /* added line */
          }
          table, th, td { border: 1px solid #000; }
          th, td { 
            padding: 5px; 
            word-wrap: break-word; /* added line */
          }
          .page-break { page-break-after: always; }
        </style>
      </head>
      <body>
        <div id="page_header"><strong>Data: ' . $dateHeader . '</strong></div>';

    $placeholders = rtrim(str_repeat('?,', count($locals)), ',');
    $sql = "SELECT p.*, u.nome AS unidade_nome
            FROM patrimonio p
            LEFT JOIN unidade u ON u.id_unidade = p.Localizacao
            WHERE p.Localizacao IN ($placeholders)";
    $result = mysqli_execute_query($conn, $sql, $locals);

    $dadosPorLocal = [];
    $nomesPorLocal = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dadosPorLocal[$row['Localizacao']][] = $row;
        $nomesPorLocal[$row['Localizacao']] = $row['unidade_nome'] ?? $row['Localizacao'];
    }

    foreach ($locals as $local) {
        $localNome = $nomesPorLocal[$local] ?? $local;
        $html .= '<div style="text-align:center; margin-bottom:10px;">
                    <strong>Local: ' . $localNome . '</strong>
                  </div>';

        $html .= '<table>
                    <thead>
                      <tr>
                        <th>Nº Patrimônio</th>
                        <th>Descrição</th>
                        <th>Data Entrada</th>
                        <th>Localização</th>
                        <th>Descrição Localização</th>
                        <th>Status</th>
                        <th>Memorando</th>
                      </tr>
                    </thead>
                    <tbody>';

        if (isset($dadosPorLocal[$local]) && count($dadosPorLocal[$local]) > 0) {
            foreach ($dadosPorLocal[$local] as $row) {
                $dataEntrada = date("d/m/Y", strtotime($row["Data_Entrada"]));
                $html .= '<tr>
                            <td>' . $row['N_Patrimonio'] . '</td>
                            <td>' . $row['Descricao'] . '</td>
                            <td>' . $dataEntrada . '</td>
                            <td>' . ($row['unidade_nome'] ?? $row['Localizacao']) . '</td>
                            <td>' . $row['Descricao_Localizacao'] . '</td>
                            <td>' . $row['Status'] . '</td>
                            <td>' . $row['Memorando'] . '</td>
                          </tr>';
            }
        } else {
            $html .= '<tr>
                        <td colspan="7" style="text-align:center;">Nenhum patrimônio encontrado</td>
                      </tr>';
        }

        $html .= '</tbody></table>
                  <div class="page-break"></div>';
    }

    $html .= '</body></html>';

    close_connection($conn);

    $dompdf = new Dompdf();
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();
    $dompdf->stream($title, ["Attachment" => false]);
} catch (Exception $e) {
    close_connection($conn);
    echo '<script>alert("Erro ao gerar relatório: ' . $e->getMessage() . '"); window.close();</script>';
    exit;
}

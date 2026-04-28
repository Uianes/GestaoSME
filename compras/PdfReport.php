<?php

declare(strict_types=1);

final class PdfReport
{
    private const PAGE_WIDTH = 842.0;
    private const PAGE_HEIGHT = 595.0;
    private const LEFT = 24.0;
    private const TOP = 550.0;
    private const ROW_HEIGHT = 24.0;

    /** @var array<int, array{headers: string[], rows: array<int, array<int, string>>}> */
    private array $tables = [];

    /** @var array<int, array<int, array{x: float, y: float, w: float, h: float, url: string}>> */
    private array $pageLinks = [];

    public function addPage(array $lines): void
    {
        $headers = [
            'ID',
            'Categoria',
            'Subcat.',
            'Orgao',
            'Modal.',
            'Licit.',
            'Ano',
            'Abertura',
            'Homol.',
            'Qtd',
            'Un',
            'Vlr Unit.',
            'Valor',
            'Link',
        ];

        $rows = [];
        foreach ($lines as $line) {
            if (is_array($line)) {
                $rows[] = array_map(static fn ($value): string => (string) $value, $line);
            }
        }

        $this->tables[] = ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    public function addTable(array $rows): void
    {
        $headers = [
            'ID',
            'Categoria',
            'Subcat.',
            'Orgao',
            'Modal.',
            'Licit.',
            'Ano',
            'Abertura',
            'Homol.',
            'Qtd',
            'Un',
            'Vlr Unit.',
            'Valor',
            'Link',
        ];

        $this->tables[] = [
            'headers' => $headers,
            'rows' => array_map(
                static fn (array $row): array => [
                    $row['id'] ?? '',
                    $row['categoria'] ?? '',
                    $row['subcategoria'] ?? '',
                    $row['orgao'] ?? '',
                    $row['modalidade'] ?? '',
                    $row['licitacao'] ?? '',
                    $row['ano'] ?? '',
                    $row['abertura'] ?? '',
                    $row['homologacao'] ?? '',
                    $row['quantidade'] ?? '',
                    $row['unidade'] ?? '',
                    $row['valor_unitario'] ?? '',
                    $row['valor_total'] ?? '',
                    $row['link'] ?? '',
                ],
                $rows
            ),
        ];
    }

    public function output(string $filename): never
    {
        if (!$this->tables) {
            $this->addTable([]);
        }

        $pageContents = $this->buildPages();
        $objects = [];
        $catalog = $this->reserve($objects);
        $pagesObject = $this->reserve($objects);
        $font = $this->reserve($objects);
        $fontBold = $this->reserve($objects);
        $pageRefs = [];

        foreach ($pageContents as $index => $content) {
            $contentObject = $this->reserve($objects);
            $pageObject = $this->reserve($objects);
            $pageRefs[] = $pageObject;
            $annotationObjects = [];

            foreach (($this->pageLinks[$index] ?? []) as $link) {
                $annotationObject = $this->reserve($objects);
                $annotationObjects[] = "{$annotationObject} 0 R";
                $objects[$annotationObject] = $this->buildLinkAnnotation($link);
            }

            $annots = $annotationObjects
                ? ' /Annots [' . implode(' ', $annotationObjects) . ']'
                : '';

            $objects[$contentObject] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
            $objects[$pageObject] = "<< /Type /Page /Parent {$pagesObject} 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] /Resources << /Font << /F1 {$font} 0 R /F2 {$fontBold} 0 R >> >> /Contents {$contentObject} 0 R{$annots} >>";
        }

        $kids = implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $pageRefs));
        $objects[$catalog] = "<< /Type /Catalog /Pages {$pagesObject} 0 R >>";
        $objects[$pagesObject] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageRefs) . " >>";
        $objects[$font] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontBold] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        ksort($objects);

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalog} 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /**
     * @return string[]
     */
    private function buildPages(): array
    {
        $pages = [];
        $this->pageLinks = [];
        $columns = [32, 72, 74, 112, 42, 50, 30, 56, 62, 34, 24, 58, 58, 90];

        foreach ($this->tables as $table) {
            $rows = $table['rows'];
            $chunks = array_chunk($rows, 18);
            if (!$chunks) {
                $chunks = [[]];
            }

            foreach ($chunks as $chunk) {
                $pageIndex = count($pages);
                $links = [];
                $content = $this->pageHeader();
                $content .= $this->drawHeaderRow($table['headers'], $columns);

                $y = self::TOP - 52;
                foreach ($chunk as $row) {
                    $content .= $this->drawDataRow($row, $columns, $y, $links);
                    $y -= self::ROW_HEIGHT;
                }

                $pages[] = $content;
                $this->pageLinks[$pageIndex] = $links;
            }
        }

        return $pages;
    }

    private function pageHeader(): string
    {
        $date = date('d/m/Y H:i');
        $content = "0 0 0 rg\nBT\n/F2 12 Tf\n24 572 Td\n(" . $this->escape($this->toWinAnsi('Pesquisa de Precos em Compras Publicas')) . ") Tj\nET\n";
        $content .= "BT\n/F1 8 Tf\n24 558 Td\n(" . $this->escape("Gerado em: {$date}") . ") Tj\nET\n";
        return $content;
    }

    /**
     * @param string[] $headers
     * @param int[] $columns
     */
    private function drawHeaderRow(array $headers, array $columns): string
    {
        $x = self::LEFT;
        $y = self::TOP - 24;
        $content = "0.78 0.80 0.80 rg\n{$x} {$y} " . array_sum($columns) . ' ' . self::ROW_HEIGHT . " re f\n";
        $content .= "0.55 0.55 0.55 RG\n0.3 w\n";
        $content .= $this->drawGrid($columns, $y);

        foreach ($headers as $index => $header) {
            $content .= "0 0 0 rg\nBT\n/F2 7 Tf\n" . ($x + 3) . ' ' . ($y + 8) . " Td\n(" . $this->escape($header) . ") Tj\nET\n";
            $x += $columns[$index];
        }

        return $content;
    }

    /**
     * @param string[] $row
     * @param int[] $columns
     * @param array<int, array{x: float, y: float, w: float, h: float, url: string}> $links
     */
    private function drawDataRow(array $row, array $columns, float $y, array &$links): string
    {
        $x = self::LEFT;
        $content = "1 1 1 rg\n{$x} {$y} " . array_sum($columns) . ' ' . self::ROW_HEIGHT . " re f\n";
        $content .= "0.72 0.72 0.72 RG\n0.25 w\n";
        $content .= $this->drawGrid($columns, $y);

        foreach ($columns as $index => $width) {
            $text = $this->asciiText($row[$index] ?? '');
            $isLink = $index === 13 && preg_match('/^https?:\\/\\//i', $text);
            $label = $isLink ? 'Abrir' : $text;
            $label = $this->fit($label, $width);

            if ($isLink) {
                $content .= "0 0 1 rg\n";
                $links[] = [
                    'x' => $x + 3,
                    'y' => $y + 5,
                    'w' => $width - 6,
                    'h' => 11,
                    'url' => $text,
                ];
            } else {
                $content .= "0 0 0 rg\n";
            }

            $content .= "BT\n/F1 6.5 Tf\n" . ($x + 3) . ' ' . ($y + 9) . " Td\n(" . $this->escape($label) . ") Tj\nET\n";
            $x += $width;
        }

        return $content;
    }

    /**
     * @param int[] $columns
     */
    private function drawGrid(array $columns, float $y): string
    {
        $x = self::LEFT;
        $width = array_sum($columns);
        $content = "{$x} {$y} {$width} " . self::ROW_HEIGHT . " re S\n";

        foreach ($columns as $column) {
            $content .= "{$x} {$y} m {$x} " . ($y + self::ROW_HEIGHT) . " l S\n";
            $x += $column;
        }
        $content .= "{$x} {$y} m {$x} " . ($y + self::ROW_HEIGHT) . " l S\n";

        return $content;
    }

    private function fit(string $value, int $width): string
    {
        $value = $this->toWinAnsi($value);
        $max = max(4, (int) floor($width / 3.7));
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 3) . '...';
    }

    private function reserve(array &$objects): int
    {
        $id = count($objects) + 1;
        $objects[$id] = '';
        return $id;
    }

    /**
     * @param array{x: float, y: float, w: float, h: float, url: string} $link
     */
    private function buildLinkAnnotation(array $link): string
    {
        $x1 = $link['x'];
        $y1 = $link['y'];
        $x2 = $link['x'] + $link['w'];
        $y2 = $link['y'] + $link['h'];
        $url = $this->escape($this->toWinAnsi($link['url']));

        return "<< /Type /Annot /Subtype /Link /Rect [{$x1} {$y1} {$x2} {$y2}] /Border [0 0 0] /A << /S /URI /URI ({$url}) >> >>";
    }

    private function toWinAnsi(string $value): string
    {
        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
    }

    private function asciiText(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['`', '^', '~'], '', $value);
    }

    private function escape(string $value): string
    {
        $escaped = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($value[$i]);
            if ($value[$i] === '\\' || $value[$i] === '(' || $value[$i] === ')') {
                $escaped .= '\\' . $value[$i];
            } elseif ($byte < 32 || $byte > 126) {
                $escaped .= '\\' . str_pad(decoct($byte), 3, '0', STR_PAD_LEFT);
            } else {
                $escaped .= $value[$i];
            }
        }

        return $escaped;
    }
}

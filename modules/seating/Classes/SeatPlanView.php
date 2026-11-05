<?php

namespace LanSuite\Module\Seating;

class SeatPlanView
{
    public function __construct(private readonly Seat2 $seats)
    {
    }

    /**
     * @param array<int,int> $sepCols
     * @param array<int,int> $sepRows
     * @param array<int,array<int,mixed>> $seatState
     * @param array<int,array<int,mixed>> $seatIp
     * @param array<int,array<int,mixed>> $seatUserid
     * @param array<int,array<int,mixed>> $seatCheckin
     * @param array<int,array<int,mixed>> $seatCheckout
     * @param array<int,array<int,array<string,mixed>>> $userInfo
     * @param array<int,mixed> $clanmates
     */
    public function render(
        array $block,
        int $blockid,
        int $mode,
        array $sepCols,
        array $sepRows,
        array $seatState,
        array $seatIp,
        array $seatUserid,
        array $seatCheckin,
        array $seatCheckout,
        array $userInfo,
        array $clanmates,
        ?array $userPaid,
        string $linktarget,
        mixed $selectedUser
    ): string {
        global $smarty, $framework, $func;

        $framework->addCSSFile('modules/seating/seating.css');
        $framework->addJavaScriptFile('modules/seating/seating-plan.js');

        $floorplan = $this->floorplanPath($block, $func);
        $floorX = (int)($block['floorplan_x'] ?? 0);
        $floorY = (int)($block['floorplan_y'] ?? 0);
        $floorScale = (int)($block['floorplan_scale'] ?? 100);
        if ($floorScale < 10 || $floorScale > 400) {
            $floorScale = 100;
        }

        $labels = [];
        foreach (['tl', 'tc', 'tr', 'lt', 'lc', 'lb', 'rt', 'rc', 'rb', 'bl', 'bc', 'br'] as $slot) {
            $labels[$slot] = (string)($block['text_' . $slot] ?? '');
        }

        $hiddenHtml = '';
        $details = [];
        $grid = $this->grid(
            $block,
            $blockid,
            $mode,
            $sepCols,
            $sepRows,
            $seatState,
            $seatIp,
            $seatUserid,
            $seatCheckin,
            $seatCheckout,
            $userInfo,
            array_map('intval', $clanmates),
            $userPaid,
            $linktarget,
            $selectedUser,
            $hiddenHtml,
            $details
        );

        $remark = '';
        if ($mode === 0) {
            $remarkText = trim((string)($block['remark'] ?? ''));
            if ($remarkText !== '') {
                $remark = $func->text2html($remarkText);
            }
        }

        $legendMine = $selectedUser ? t('Auswahl') : t('Dein Platz');
        $smarty->assign('seat_remark', $remark);
        $smarty->assign('seat_mode', $mode);
        $smarty->assign('seat_ui', $this->uiText());
        $smarty->assign('seat_legend_mine', $legendMine);
        $smarty->assign('seat_labels', $labels);
        $smarty->assign('seat_show_top', $labels['tl'] !== '' || $labels['tc'] !== '' || $labels['tr'] !== '');
        $smarty->assign('seat_show_bottom', $labels['bl'] !== '' || $labels['bc'] !== '' || $labels['br'] !== '');
        $smarty->assign('seat_show_left', $labels['lt'] !== '' || $labels['lc'] !== '' || $labels['lb'] !== '');
        $smarty->assign('seat_show_right', $labels['rt'] !== '' || $labels['rc'] !== '' || $labels['rb'] !== '');
        $smarty->assign('seat_room_tools', $this->roomTools());
        $smarty->assign('seat_grid', $grid);
        $smarty->assign('seat_hidden', $hiddenHtml);
        $showFloor = $floorplan !== '' && ($mode === 0 || $mode === 4);
        $smarty->assign('seat_floorplan', $showFloor ? $floorplan : '');
        $smarty->assign('seat_floor_x', $floorX);
        $smarty->assign('seat_floor_y', $floorY);
        $smarty->assign('seat_floor_scale', $floorScale);
        $smarty->assign('seat_show_align', $floorplan !== '' && $mode === 4);
        $smarty->assign('seat_aisles', $mode === 4 ? $this->aisleWidthFields($block, $sepRows, $sepCols) : []);
        $smarty->assign(
            'seat_json',
            json_encode($details, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
        );

        return $smarty->fetch('modules/seating/templates/plan_grid.htm');
    }

    /**
     * @param array<string,mixed> $block
     */
    private function floorplanPath(array $block, object $func): string
    {
        $path = str_replace('\\', '/', (string)($block['floorplan'] ?? ''));
        if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'ext_inc/seating/')) {
            return '';
        }
        if (!$func->chk_img_path($path)) {
            return '';
        }

        return $path;
    }

    /**
     * @return array<string,string>
     */
    private function uiText(): array
    {
        return [
            'selected' => t('Ausgewählt'),
            'tools' => t('Werkzeuge'),
            'seat' => t('Platz'),
            'empty' => t('Leer'),
            'locked' => t('Gesperrt'),
            'room' => t('Raum'),
            'letter' => t('Beschriftung'),
            'letter_unknown' => t('Dieses Zeichen gibt es im Plan nicht'),
            'occupied_note' => t('Belegte Plätze bleiben unverändert.'),
            'free' => t('Frei'),
            'taken' => t('Besetzt'),
            'marked' => t('Vorgemerkt'),
            'clan' => t('Clanmate'),
            'checked_out' => t('Frei (Ausgecheckt)'),
            'checked_in' => t('Besetzt (Eingecheckt)'),
            'zoom_in' => t('Größer'),
            'zoom_out' => t('Kleiner'),
            'align' => t('Grundriss ausrichten'),
            'offset_x' => t('Versatz horizontal'),
            'offset_y' => t('Versatz vertikal'),
            'scale' => t('Skalierung in Prozent'),
            'floorplan' => t('Grundriss'),
            'tap' => t('Tippe auf einen Sitzplatz, um Details und Aktionen zu sehen.'),
            'aisle' => t('Gang'),
            'aisle_title' => t('Gang einfügen oder entfernen'),
            'aisle_widths' => t('Gangbreiten in Pixeln'),
            'aisle_after_col' => t('Gang nach Spalte %1'),
            'aisle_after_row' => t('Gang nach Reihe %1'),
            'fill_col' => t('Spalte füllen'),
            'fill_row' => t('Reihe füllen'),
            'place' => t('Platz'),
            'username' => t('Benutzername'),
            'name' => t('Name'),
            'clan_label' => t('Clan'),
            'status' => t('Status'),
            'ip' => t('IP'),
            'no_ip' => t('Keine zugeordnet'),
            'description' => t('Beschreibung'),
            'wc' => t('WC'),
            'exit' => t('Notausgang'),
            'catering' => t('Catering'),
            'choose' => t('Diesen Sitzplatz auswählen'),
            'reserve' => t('Reservieren'),
            'mark' => t('Vormerken'),
            'release' => t('Freigeben'),
        ];
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function roomTools(): array
    {
        $names = [
            10 => t('Wand senkrecht'),
            11 => t('Wand waagrecht'),
            12 => t('Wand T nach oben'),
            13 => t('Wand T nach links'),
            14 => t('Wand T nach unten'),
            15 => t('Wand T nach rechts'),
            16 => t('Wand Kreuz'),
            17 => t('Ecke oben links'),
            18 => t('Ecke unten links'),
            19 => t('Ecke unten rechts'),
            20 => t('Ecke oben rechts'),
            21 => t('Diagonale links unten'),
            22 => t('Diagonale rechts unten'),
            23 => t('Diagonale rechts oben'),
            24 => t('Diagonale links oben'),
            101 => t('Linie waagrecht'),
            102 => t('Linie senkrecht'),
            103 => t('Ecke dünn unten rechts'),
            104 => t('Ecke dünn unten links'),
            105 => t('Ecke dünn oben rechts'),
            106 => t('Ecke dünn oben links'),
            107 => t('T dünn nach unten'),
            108 => t('T dünn nach rechts'),
            109 => t('T dünn nach links'),
            110 => t('T dünn nach oben'),
            111 => t('Kreuz dünn'),
            112 => t('Diagonale dünn rechts unten'),
            113 => t('Diagonale dünn links unten'),
            114 => t('Diagonale dünn rechts oben'),
            115 => t('Diagonale dünn links oben'),
            132 => t('Fenster senkrecht'),
            133 => t('Fenster oben spitz'),
            134 => t('Fenster doppelt senkrecht'),
            135 => t('Fenster unten spitz'),
            136 => t('Fenster Ecke unten links'),
            137 => t('Fenster Ecke unten rechts'),
            138 => t('Fenster Ecke oben links'),
            139 => t('Fenster Ecke oben rechts'),
            140 => t('Fenster Ecke rechts unten'),
            141 => t('Fenster Ecke links unten'),
            142 => t('Fenster Ecke rechts oben'),
            143 => t('Fenster Ecke links oben'),
            144 => t('Fenster Doppel-Ecke rechts unten'),
            145 => t('Fenster Doppel-Ecke links unten'),
            146 => t('Fenster Doppel-Ecke rechts oben'),
            147 => t('Fenster Doppel-Ecke links oben'),
            148 => t('Fenster-Ecke unten links'),
            149 => t('Fenster-Ecke unten rechts'),
            150 => t('Fenster-Ecke oben rechts'),
            151 => t('Fenster-Ecke oben links'),
            152 => t('Fenster waagrecht'),
            153 => t('Fenster links spitz'),
            154 => t('Fenster doppelt waagrecht'),
            156 => t('Fenster rechts spitz'),
            201 => t('Markierung blau Punkt'),
            206 => t('Markierung blau Ecke oben links'),
            207 => t('Markierung blau senkrecht links'),
            208 => t('Markierung blau Ecke unten links'),
            209 => t('Markierung blau Ecke unten rechts'),
            212 => t('Markierung blau waagrecht oben'),
            213 => t('Markierung blau Ecke oben rechts'),
            216 => t('Markierung blau senkrecht rechts'),
            220 => t('Markierung blau waagrecht unten'),
            222 => t('Markierung gelb Punkt'),
            223 => t('Markierung gelb rechts'),
            224 => t('Markierung gelb waagrecht'),
            225 => t('Markierung gelb links'),
            226 => t('Markierung gelb unten'),
            227 => t('Markierung gelb senkrecht'),
            228 => t('Markierung gelb oben'),
        ];
        $tools = [];
        foreach ($names as $id => $name) {
            $tools[] = ['id' => $id, 'name' => $name];
        }

        return $tools;
    }

    /**
     * @param array<string,mixed> $block
     * @param array<int,int> $sepCols
     * @param array<int,int> $sepRows
     * @param array<string,mixed> $details
     */
    private function grid(
        array $block,
        int $blockid,
        int $mode,
        array $sepCols,
        array $sepRows,
        array $seatState,
        array $seatIp,
        array $seatUserid,
        array $seatCheckin,
        array $seatCheckout,
        array $userInfo,
        array $clanmates,
        ?array $userPaid,
        string $linktarget,
        mixed $selectedUser,
        ?string &$hiddenHtml,
        ?array &$details
    ): string {
        global $auth;

        $hidden = [];
        $details = [];
        $cols = (int)$block['cols'];
        $rows = (int)$block['rows'];
        $orientation = $block['orientation'] ?? 0;
        $action = $_GET['action'] ?? 'edit';
        if (!in_array($action, ['edit', 'add'], true)) {
            $action = 'edit';
        }
        $ui = $this->uiText();
        $compareUser = $selectedUser ? (int)$selectedUser : (int)($auth['userid'] ?? 0);

        $columns = [];
        for ($x = 0; $x <= $cols; $x++) {
            $columns[] = ['kind' => 'seat', 'index' => $x];
            $aisleValue = $x + 1;
            $active = !empty($sepRows[$aisleValue]);
            if ($mode === 1 || $active) {
                $columns[] = [
                    'kind' => 'aisle',
                    'value' => $aisleValue,
                    'active' => $active,
                    'param' => 'change_sep_row',
                ];
            }
        }

        $html = '<table class="seatplan__grid">';
        $html .= '<tr><th class="seatplan__corner"></th>';
        foreach ($columns as $column) {
            if ($column['kind'] === 'seat') {
                $name = (string)$this->seats->CoordinateToName($column['index'] + 1, -1, $orientation);
                $html .= '<th>' . $this->axisLabel($mode, $name, 'data-fill-col', (int)$column['index'], $ui['fill_col']) . '</th>';
            } else {
                $width = $this->aisleWidth($sepRows, (int)$column['value']);
                $html .= '<th class="seatplan__aisle' . ($column['active'] ? ' is-on' : '') . '"' . $this->aisleMarker(1, (int)$column['value'], $width) . '>';
                $html .= $this->aisleControl($mode, $blockid, $action, $column['param'], (int)$column['value'], (bool)$column['active'], $ui);
                $html .= '</th>';
            }
        }
        $html .= '</tr>';

        for ($y = 0; $y <= $rows; $y++) {
            $rowName = (string)$this->seats->CoordinateToName(-1, $y, $orientation);
            $html .= '<tr><th>' . $this->axisLabel($mode, $rowName, 'data-fill-row', $y, $ui['fill_row']) . '</th>';
            foreach ($columns as $column) {
                if ($column['kind'] === 'aisle') {
                    $width = $this->aisleWidth($sepRows, (int)$column['value']);
                    $html .= '<td class="seatplan__aisle' . ($column['active'] ? ' is-on' : '') . '"' . $this->aisleMarker(1, (int)$column['value'], $width) . '></td>';
                    continue;
                }
                $x = (int)$column['index'];
                $stored = $seatState[$y][$x] ?? '';
                $raw = ($stored === '' || $stored === null) ? 0 : (int)$stored;
                $symbol = ($raw === 100) ? 0 : $raw;
                $seatUser = (int)($seatUserid[$y][$x] ?? 0);
                if ($raw === 2) {
                    $symbol = $this->occupiedSymbol(
                        $seatUser,
                        $compareUser,
                        $clanmates,
                        $seatCheckout[$y][$x] ?? '',
                        $seatCheckin[$y][$x] ?? ''
                    );
                }
                $locked = $mode === 2 && $raw > 1 && $raw < 7;
                $coord = (string)$this->seats->CoordinateToName($x + 1, $y, $orientation);
                $label = trim((string)($block['name'] ?? '') . ' ' . $coord);
                $html .= '<td>' . $this->cellMarkup($mode, $symbol, $locked, $x, $y, $label) . '</td>';
                if ($mode === 2) {
                    $value = ($stored === '' || $stored === null) ? '' : (string)$raw;
                    $key = $x * 100 + $y;
                    $hidden[] = '<input id="cell' . $key . '" name="cell[' . $key . ']" value="' . $this->e($value) . '" type="hidden">';
                }
                if ($mode === 0 && $symbol !== 0) {
                    $details[$y . '_' . $x] = $this->detail(
                        $block,
                        $coord,
                        $raw,
                        $symbol,
                        $seatUser,
                        $userInfo[$y][$x] ?? [],
                        $seatIp[$y][$x] ?? '',
                        $blockid,
                        $y,
                        $x,
                        $userPaid,
                        $linktarget,
                        $selectedUser,
                        $ui
                    );
                }
            }
            $html .= '</tr>';

            $aisleValue = $y + 1;
            $active = !empty($sepCols[$aisleValue]);
            if ($mode === 1 || $active) {
                $width = $this->aisleWidth($sepCols, $aisleValue);
                $marker = $this->aisleMarker(0, $aisleValue, $width);
                $aisleClass = 'seatplan__aisle' . ($active ? ' is-on' : '');
                $html .= '<tr class="seatplan__aisle-row' . ($active ? ' is-on' : '') . '"' . $marker . '>';
                $html .= '<th class="' . $aisleClass . '"' . $marker . '>';
                $html .= $this->aisleControl($mode, $blockid, $action, 'change_sep_col', $aisleValue, $active, $ui);
                $html .= '</th><td class="' . $aisleClass . '" colspan="' . count($columns) . '"' . $marker . '><span class="seatplan__aisle-gap"' . $marker . '></span></td></tr>';
            }
        }
        $html .= '</table>';
        $hiddenHtml = implode('', $hidden);

        return $html;
    }

    /**
     * @param array<int,int> $clanmates
     */
    private function occupiedSymbol(int $seatUser, int $compareUser, array $clanmates, mixed $checkout, mixed $checkin): int
    {
        if ($compareUser > 0 && $seatUser === $compareUser) {
            return 4;
        }
        if (in_array($seatUser, $clanmates, true)) {
            return 5;
        }
        if ($this->isTimestamp($checkout)) {
            return 6;
        }
        if ($this->isTimestamp($checkin)) {
            return 8;
        }

        return 2;
    }

    private function isTimestamp(mixed $value): bool
    {
        return is_string($value) && $value !== '' && $value !== '0000-00-00 00:00:00';
    }

    private function axisLabel(int $mode, string $name, string $attribute, int $index, string $title): string
    {
        if ($mode === 2) {
            return '<button type="button" class="seatplan__fill" ' . $attribute . '="' . $index . '" title="' . $this->e($title) . '">' . $this->e($name) . '</button>';
        }

        return '<span>' . $this->e($name) . '</span>';
    }

    /**
     * @param array<string,string> $ui
     */
    private function aisleControl(int $mode, int $blockid, string $action, string $param, int $value, bool $active, array $ui): string
    {
        if ($mode !== 1) {
            return '';
        }
        $href = 'index.php?mod=seating&action=' . rawurlencode($action)
            . '&step=4&blockid=' . $blockid
            . '&' . $param . '=' . $value;

        return '<a class="seatplan__aisle-btn' . ($active ? ' is-on' : '') . '" href="' . $this->e($href) . '" title="' . $this->e($ui['aisle_title']) . '" aria-pressed="' . ($active ? 'true' : 'false') . '">' . $this->e($ui['aisle']) . '</a>';
    }

    /**
     * @param array<int,int> $widths
     */
    private function aisleWidth(array $widths, int $value): int
    {
        $width = (int)($widths[$value] ?? 14);
        if ($width < 4 || $width > 400) {
            return 14;
        }

        return $width;
    }

    private function aisleMarker(int $orientation, int $value, int $width): string
    {
        return ' data-aisle-orient="' . $orientation . '" data-aisle-value="' . $value . '" style="--aisle-size:' . $width . 'px"';
    }

    /**
     * @param array<string,mixed> $block
     * @param array<int,int> $sepRows
     * @param array<int,int> $sepCols
     * @return list<array{orient:int,value:int,width:int,label:string}>
     */
    private function aisleWidthFields(array $block, array $sepRows, array $sepCols): array
    {
        $orientation = $block['orientation'] ?? 0;
        $fields = [];
        foreach ($sepRows as $value => $width) {
            $name = (string)$this->seats->CoordinateToName((int)$value, -1, $orientation);
            $width = $this->aisleWidth($sepRows, (int)$value);
            $fields[] = [
                'orient' => 1,
                'value' => (int)$value,
                'width' => $width,
                'label' => t('Gang nach Spalte %1', $name),
            ];
        }
        foreach ($sepCols as $value => $width) {
            $name = (string)$this->seats->CoordinateToName(-1, (int)$value - 1, $orientation);
            $width = $this->aisleWidth($sepCols, (int)$value);
            $fields[] = [
                'orient' => 0,
                'value' => (int)$value,
                'width' => $width,
                'label' => t('Gang nach Reihe %1', $name),
            ];
        }

        return $fields;
    }

    private function cellMarkup(int $mode, int $symbol, bool $locked, int $x, int $y, string $label): string
    {
        $key = $x * 100 + $y;
        $attrs = ' data-symbol="' . $symbol . '" data-row="' . $y . '" data-col="' . $x . '" data-key="' . $y . '_' . $x . '" data-field="cell' . $key . '"';
        if ($locked) {
            $attrs .= ' data-locked="1"';
        }
        $glyph = '<span class="seatplan__glyph" data-symbol="' . $symbol . '"></span>';
        if ($mode === 2 || (($mode === 0 || $mode === 4) && $symbol !== 0)) {
            return '<button type="button" class="seatplan__cell" aria-label="' . $this->e($label) . '"' . $attrs . '>' . $glyph . '</button>';
        }

        return '<span class="seatplan__cell"' . $attrs . '>' . $glyph . '</span>';
    }

    /**
     * @param array<string,mixed> $block
     * @param array<string,mixed> $user
     * @param array<string,string> $ui
     * @return array{title:string,lines:list<array{0:string,1:string}>,image:string,action:string,actionLabel:string}
     */
    private function detail(
        array $block,
        string $coord,
        int $raw,
        int $symbol,
        int $seatUser,
        array $user,
        mixed $ipValue,
        int $blockid,
        int $row,
        int $col,
        ?array $userPaid,
        string $linktarget,
        mixed $selectedUser,
        array $ui
    ): array {
        global $func;

        $title = trim((string)($block['name'] ?? '') . ' ' . $coord);
        $lines = [[$ui['place'], $title]];
        $username = trim((string)($user['username'] ?? ''));
        if ($username !== '' && ($raw === 2 || $raw === 3)) {
            $lines[] = [$ui['username'], $username];
            if ($this->maySeePersonal($seatUser, $selectedUser)) {
                $personal = trim(trim((string)($user['firstname'] ?? '')) . ' ' . trim((string)($user['name'] ?? '')));
                if ($personal !== '') {
                    $lines[] = [$ui['name'], $personal];
                }
            }
            $lines[] = [$ui['clan_label'], trim((string)($user['clan'] ?? ''))];
        }

        $description = $this->facilityLabel($raw, $ui);
        $status = $this->statusLabel($symbol, $ui, (bool)$selectedUser);
        if ($description !== '') {
            $lines[] = [$ui['description'], $description];
        } elseif ($status !== '') {
            $lines[] = [$ui['status'], $status];
        }

        if (in_array($raw, [1, 2, 3, 7], true)) {
            $ip = $this->formatIp($ipValue);
            $lines[] = [$ui['ip'], $ip !== '' ? $ip : $ui['no_ip']];
        }

        $image = '';
        $avatar = (string)($user['avatar_path'] ?? '');
        $showPicture = $func->chk_img_path($avatar) && ($raw === 2 || $raw === 3) && (
            !empty($GLOBALS['cfg']['seating_show_user_pics']) || $this->maySeePersonal($seatUser, $selectedUser)
        );
        if ($showPicture) {
            $image = $avatar;
        }

        $action = $this->seatHref($raw, $seatUser, $blockid, $row, $col, $userPaid, $linktarget);
        return [
            'title' => $title,
            'lines' => $lines,
            'image' => $image,
            'action' => $action,
            'actionLabel' => $this->actionLabel($action, $linktarget, $ui),
        ];
    }

    /**
     * @param array<string,string> $ui
     */
    private function statusLabel(int $symbol, array $ui, bool $selectedUser): string
    {
        return match ($symbol) {
            1 => $ui['free'],
            2 => $ui['taken'],
            3 => $ui['marked'],
            4 => $selectedUser ? t('Auswahl') : t('Dein Platz'),
            5 => $ui['clan'],
            6 => $ui['checked_out'],
            7 => $ui['locked'],
            8 => $ui['checked_in'],
            default => '',
        };
    }

    /**
     * @param array<string,string> $ui
     */
    private function facilityLabel(int $raw, array $ui): string
    {
        return match ($raw) {
            80, 81 => $ui['wc'],
            82 => $ui['exit'],
            83 => $ui['catering'],
            default => '',
        };
    }

    private function maySeePersonal(int $seatUser, mixed $selectedUser): bool
    {
        global $cfg, $auth;

        if (empty($cfg['sys_internet']) || (int)($auth['type'] ?? 0) > \LS_AUTH_TYPE_USER) {
            return true;
        }

        return $selectedUser && (int)($auth['userid'] ?? 0) === $seatUser;
    }

    private function formatIp(mixed $ip): string
    {
        if (!is_string($ip) || $ip === '') {
            return '';
        }
        if (preg_match('/[^\x20-\x7E]/', $ip) === 1) {
            $text = @inet_ntop($ip);
            return is_string($text) ? $text : '';
        }

        return $ip;
    }

    private function seatHref(int $raw, int $seatUser, int $blockid, int $row, int $col, ?array $userPaid, string $linktarget): string
    {
        global $auth;

        if ($linktarget !== '') {
            return $linktarget . '&row=' . $row . '&col=' . $col;
        }
        if (empty($auth['login'])) {
            return '';
        }
        $paid = is_array($userPaid) && !empty($userPaid['paid']);
        $base = 'index.php?mod=seating&action=show&blockid=' . $blockid . '&row=' . $row . '&col=' . $col;
        if ($raw === 1 && !$paid) {
            return $base . '&step=12';
        }
        if ($raw === 1 || ($raw === 3 && $seatUser !== (int)($auth['userid'] ?? 0))) {
            return $base . '&step=10';
        }
        if (($raw === 2 || $raw === 3) && $seatUser === (int)($auth['userid'] ?? 0)) {
            return $base . '&step=20';
        }

        return '';
    }

    /**
     * @param array<string,string> $ui
     */
    private function actionLabel(string $href, string $linktarget, array $ui): string
    {
        if ($href === '') {
            return '';
        }
        if ($linktarget !== '') {
            return $ui['choose'];
        }
        if (str_contains($href, 'step=12')) {
            return $ui['mark'];
        }
        if (str_contains($href, 'step=20')) {
            return $ui['release'];
        }

        return $ui['reserve'];
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

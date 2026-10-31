<?php

namespace LanSuite\Module\Seating;

class Seat2
{
    /**
     * @param int $userid
     * @param int $MaxBlockLength
     * @param string $break
     * @return string
     */
    public function SeatNameLink($userid, $MaxBlockLength = 0, $break = '<br />')
    {
        global $db, $database, $party;

        // Unterscheidung Bezahlt oder Unbezahlt (aber nur 1 res. Platz)
        $seat_paid = $database->queryWithOnlyFirstRow("
          SELECT
            paid
          FROM %prefix%party_user
          WHERE
            party_id = ?
            AND user_id = ?", [$party->party_id, $userid]);

        if ($seat_paid && $seat_paid['paid'] > 0) {
            $seat_status = 2;
        } else {
            $seat_status = 3;
        };

        if (!$userid) {
            return '';
        } else {
            $row = $database->queryWithOnlyFirstRow("
              SELECT
                b.blockid,
                b.name,
                b.orientation,
                s.col,
                s.row
              FROM %prefix%seat_block AS b
              LEFT JOIN %prefix%seat_seats AS s ON b.blockid = s.blockid
              WHERE
                b.party_id = ?
                AND s.userid = ?
                AND s.status = ?", [$party->party_id, $userid, $seat_status]);
        }

        if (!$row || !$row['blockid']) {
            return '';

        } else {
            $LinkText = $row['name'] .' '. $break . $this->CoordinateToName($row['col'] + 1, $row['row'], $row['orientation']);
            return "<a href=\"#\" onclick=\"var w=window.open('index.php?mod=seating&action=popup&design=popup&function=usrmgr&id={$row['blockid']}&userarray[]=$userid&l=1','_blank','width=596,height=678,resizable=yes');\" class=\"small\">$LinkText</a>";
        }
    }

    /**
     * @param int $userid
     * @param int $MaxBlockLength
     * @param int $LinkIt
     */
    public function SeatOfUser($userid, $MaxBlockLength = 0, $LinkIt = 0): bool|string
    {
        global $db, $database, $party;

        // Unterscheidung Bezahlt oder Unbezahlt (aber nur 1 res. Platz)
        $seat_paid = $database->queryWithOnlyFirstRow("
          SELECT
            paid
          FROM %prefix%party_user
          WHERE
            party_id = ?
            AND user_id = ?", [$party->party_id, $userid]);

        if ($seat_paid['paid']>0) {
            $seat_status = 2;
        } else {
            $seat_status = 3;
        };

        $row = $database->queryWithOnlyFirstRow("
          SELECT
            s.row,
            s.col,
            b.blockid,
            b.name
          FROM %prefix%seat_seats AS s
          LEFT JOIN %prefix%seat_block AS b ON s.blockid = b.blockid
          WHERE
            s.userid = ?
            AND s.status = ?
            AND b.party_id = ?", [$userid, $seat_status, $party->party_id]);

        if ($row['blockid']) {
            return $this->CoordinateToBlockAndName($row['col'] + 1, $row['row'], $row['blockid'], $MaxBlockLength, $LinkIt, $userid);
        } else {
            return false;
        }
    }

    /**
     * @param int $userid
     */
    public function SeatOfUserArray($userid): array|bool
    {
        global $db, $database, $party;

        // Unterscheidung Bezahlt oder Unbezahlt (aber nur 1 res. Platz)
        $seat_paid = $database->queryWithOnlyFirstRow("
          SELECT
            paid
          FROM %prefix%party_user
          WHERE
            party_id = ?
            AND user_id = ?", [$party->party_id, $userid]);

        $seatPaidPaid = $seat_paid['paid'] ?? 0;
        if ($seatPaidPaid > 0) {
            $seat_status = 2;
        } else {
            $seat_status = 3;
        };

        $row = $database->queryWithOnlyFirstRow("
          SELECT
            s.row,
            s.col,
            s.ip,
            b.blockid,
            b.name
          FROM %prefix%seat_seats AS s
          LEFT JOIN %prefix%seat_block AS b ON s.blockid = b.blockid
          WHERE
            s.userid = ?
            AND s.status = ?
            AND b.party_id = ?", [$userid, $seat_status, $party->party_id]);

        $blockID = $row['blockid'] ?? 0;
        if ($blockID) {
            $arr = array();
            $arr['block'] = $row['blockid'];
            $arr['row'] = $row['row'];
            $arr['col'] = $row['col'];
            $arr['ip'] = $row['ip'];
            return $arr;
        } else {
            return false;
        }
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $blockid
     * @param int $MaxBlockLength
     * @param int $LinkIt
     * @param int $userid
     */
    private function CoordinateToBlockAndName($x, $y, $blockid, $MaxBlockLength = 0, $LinkIt = 0, $userid = 0): bool|string
    {
        global $db, $database;

        $row = $database->queryWithOnlyFirstRow("
          SELECT
            name,
            orientation
          FROM %prefix%seat_block
          WHERE blockid = ?", [$blockid]);

        if (!$row['name']) {
            return false;
        } else {
            if ($MaxBlockLength > 4 and strlen($row['name']) > $MaxBlockLength) {
                $row['name'] = substr($row['name'], 0, $MaxBlockLength - 3) . '...';
            }

            $LinkText = $row['name'] .' - '. $this->CoordinateToName($x, $y, $row['orientation']);
            if ($LinkIt == 1) {
                return "<a href=\"index.php?mod=seating&action=show&step=2&blockid=$blockid&col=$x&row=$y\">$LinkText</a>";
            }
            if ($LinkIt == 2) {
                return "<a href=\"#\" onclick=\"var w=window.open('index.php?mod=seating&action=popup&design=popup&function=usrmgr&id=$blockid&userarray[]={$userid}&l=1','_blank','width=596,height=638,resizable=yes');\">$LinkText</a>";
            } else {
                return $LinkText;
            }
        }
    }

    /**
     * @param int $x
     * @param int $y
     * @param boolean $orientation
     */
    public function CoordinateToName($x, $y, $orientation): int|string
    {
        $out = '';
        if ($orientation) {
            if ($y > -1) {
                $out = ($y + 1);
            }
            if ($x > -1 and $y > -1) {
                $out .= '-';
            }
            if ($x > -1) {
                $out .= str_replace('@', '', chr(64 + floor(($x - 1) / 26))) . chr(65 + ($x - 1) % 26);
            }
        } else {
            if ($y > -1) {
                $out = str_replace('@', '', chr(64 + floor(($y) / 26))) . chr(65 + ($y) % 26);
            }
            if ($x > -1 and $y > -1) {
                $out .= '-';
            }
            if ($x > -1) {
                $out .= ($x);
            }
        }

        return $out;
    }

    /**
     * @param int $x
     * @param boolean $orientation
     * @return string
     */
    public function CoordinateToNameCol($x, $orientation)
    {
        $out = '';
        if ($orientation) {
            if ($x > -1) {
                $out .= str_replace('@', '', chr(64 + floor(($x) / 26))) . chr(65 + ($x) % 26);
            }
        } else {
            if ($x > -1) {
                $out .= ($x + 1);
            }
        }

        return $out;
    }

    /**
     * @param int $y
     * @param boolean $orientation
     */
    public function CoordinateToNameRow($y, $orientation): int|string
    {
        $out = '';
        if ($orientation) {
            if ($y > -1) {
                $out = ($y + 1);
            }
        } else {
            if ($y > -1) {
                $out = str_replace('@', '', chr(64 + floor(($y) / 26))) . chr(65 + ($y) % 26);
            }
        }

        return $out;
    }

    /**
     * @param int       $id     Can be a User ID or Block ID
     * @param string    $idtype Can be "u" for User ID (standard) or "b" for Block ID
     * @return bool             True means the block is a U18 block. False means that is a over 18 block or the block doesn't exist
     */
    public function U18Block($id, $idtype)
    {
        $blockid = null;
        global $db, $database;

        if ($idtype == "b") {
            $blockid = $id;
        } elseif ($idtype != "b") {
            $row_seat = $database->queryWithOnlyFirstRow("SELECT blockid FROM %prefix%seat_seats WHERE userid = ?", [$id]);
            $blockid = $row_seat['blockid'] ?? '';
            if ($blockid == "") {
                return false;
            }
        }

        $row_block = $database->queryWithOnlyFirstRow("SELECT u18, blockid FROM %prefix%seat_block WHERE blockid = ?", [$blockid]);
        $blockid = $row_block['blockid'];
        if ($blockid == "") {
            return false;
        }

        $u18 = $row_block["u18"];
        if ($u18 == true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int       $blockid
     * @param int       $mode           Possible modes: 0 = Normal display mode, 1 = With seperators, 2 = With checkboxes, 3 = Admin mode
     * @param string    $linktarget
     * @param bool      $selected_user
     * @return string
     * @throws \Exception
     * @throws \SmartyException
     */
    public function DrawPlan($blockid, $mode, $linktarget = '', $selected_user = false)
    {
        $seat_user_checkin = [];
        $party_user = [];
        $seat_user_checkout = [];
        $user_info = [];
        $YStartPlan = null;
        $jscode = null;
        $XStartPlan = null;
        $YOffset = null;
        global $db, $database, $templ, $auth, $cfg, $party, $smarty, $framework, $func;

        // Get Block data (side descriptions + number of rows + cols)
        $block = $database->queryWithOnlyFirstRow("SELECT * FROM %prefix%seat_block WHERE blockid = ?", [$blockid]);

        // If we don't have a seatplan or block ID, we don't need to render something.
        if (!$block) {
            return '';
        }

        $smarty->assign('row_count', $block['rows'] + 1);
        $smarty->assign('col_count', $block['cols'] + 1);
        $smarty->assign('mode', $mode);

        // Get seperators
        $sep_cols = array();
        $sep_rows = array();
        $seperators = $db->qry("SELECT orientation, value, width FROM %prefix%seat_sep WHERE blockid = %int%", $blockid);
        while ($seperator = $db->fetch_array($seperators)) {
            $width = (int)($seperator['width'] ?? 14);
            if ($width < 4 || $width > 400) {
                $width = 14;
            }
            if ($seperator['orientation'] == 0) {
                $sep_cols[$seperator['value']] = $width;
            } else {
                $sep_rows[$seperator['value']] = $width;
            }
        }
        $db->free_result($seperators);

        // Store seat-data in arrays
        $seat_state = array();
        $seat_ip = array();
        $seat_userid = array();
        $seats_qry = $db->qry('
          SELECT
            s.*,
            u.*,
            c.name AS clan,
            c.url AS clanurl,
            u.avatar_path
          FROM %prefix%seat_seats AS s
          LEFT JOIN %prefix%user AS u ON u.userid = s.userid
          LEFT JOIN %prefix%clan AS c ON u.clanid = c.clanid
          WHERE
            blockid = %int%', $blockid);

        if (!$db->num_rows() == 0) {
            while ($seat_row = $db->fetch_array($seats_qry)) {
                if ($seat_row['userid']) {
                    $party_user = $database->queryWithOnlyFirstRow("
                      SELECT
                        checkin,
                        checkout
                      FROM %prefix%party_user
                      WHERE
                        user_id = ?
                        AND party_id = ?", [$seat_row['userid'], $party->party_id]);
                }

                $partyUserCheckin = 0;
                $partyUserCheckout = 0;
                if (is_array($party_user) && array_key_exists('checkin', $party_user) && array_key_exists('checkout', $party_user)) {
                    $partyUserCheckin = $party_user['checkin'];
                    $partyUserCheckout = $party_user['checkout'];
                }

                $seat_state[$seat_row['row']][$seat_row['col']] = $seat_row['status'];
                $seat_ip[$seat_row['row']][$seat_row['col']] = $seat_row['ip'];
                $seat_userid[$seat_row['row']][$seat_row['col']] = $seat_row['userid'];
                $seat_user_checkin[$seat_row['row']][$seat_row['col']] = $partyUserCheckin;
                $seat_user_checkout[$seat_row['row']][$seat_row['col']] = $partyUserCheckout;
                $user_info[$seat_row['row']][$seat_row['col']] = $seat_row;
            }
            $db->free_result($seats_qry);
        }

        // Get current users clanmates
        $my_clanmates = array();
        if ($auth['clanid']) {
            $clanmates = $db->qry("SELECT userid FROM %prefix%user WHERE clanid = %int%", $auth['clanid']);
            while ($clanmate = $db->fetch_array($clanmates)) {
                $my_clanmates[] = $clanmate['userid'];
            }
            $db->free_result($clanmates);
        }

        // Has user paid?
        $user_paid = null;
        if ($auth['login']) {
            $user_paid = $database->queryWithOnlyFirstRow("SELECT paid FROM %prefix%party_user WHERE user_id = ? AND party_id = ?", [$auth['userid'], $party->party_id]);
        }

        // Modes 0–2 and 4 are the visitor plan, the editor and floor-plan alignment. Mode 3 stays the IP table.
        if ((int)$mode !== 3) {
            $view = new SeatPlanView($this);
            return $view->render(
                $block,
                (int)$blockid,
                (int)$mode,
                $sep_cols,
                $sep_rows,
                $seat_state,
                $seat_ip,
                $seat_userid,
                $seat_user_checkin,
                $seat_user_checkout,
                $user_info,
                $my_clanmates,
                $user_paid,
                (string)$linktarget,
                $selected_user
            );
        }

        // Header-Row
        if ($mode == 3) {
            $head = array();
            for ($x = 0; $x <= $block['cols']; $x++) {
                if (array_key_exists($x+1, $sep_rows) && $sep_rows[$x+1]) {
                    $head[$x]['width'] = 28;
                    $head[$x]['icon'] = "design/{$auth['design']}/images/arrows_seating_remove_sep_hor.gif";
                } else {
                    $head[$x]['width'] = 14;
                    $head[$x]['icon'] = "design/{$auth['design']}/images/arrows_seating_add_sep_hor.gif";
                }
                $head[$x]['link'] = "index.php?mod=seating&action={$_GET['action']}&step=4&blockid=$blockid" . "&change_sep_row=".($x + 1);
                $head[$x]['name'] = $this->CoordinateToName($x + 1, -1, $block['orientation']);
            }
            $smarty->assign('head', $head);
        }

        $YStartPlan = 0;
        $XStartPlan = 0;
        $jscode = '';
        $cell_nr = 0;
        $body = array();
        $sepY = 0;
        for ($y = 0; $y <= $block['rows']; $y++) {
            if (array_key_exists($y, $sep_cols) && $sep_cols[$y]) {
                $sepY++;
            }
            $YOffset = $y * 14 + $sepY * 7 + $YStartPlan;

            $body[$y]['desc'] = $this->CoordinateToName(-1, $y, $block['orientation']);
            if ($mode != 3) {
                $jscode .= "CreateText('". $this->CoordinateToName(-1, $y, $block['orientation']) ."', ". ($XStartPlan - 10) .", ". ($YOffset + 9) .", '');\n";
            }
            if ($mode == 1) {
                if (array_key_exists($y+1, $sep_cols) && $sep_cols[$y+1]) {
                    $jscode .= "CreateSmallText('^', ". ($XStartPlan - 20) .", ". ($YOffset + 9 + 7) .", 'index.php?mod=seating&action=edit&step=4&blockid=". $_GET['blockid'] ."&change_sep_col=". ($y + 1) ."');\n";
                } else {
                    $jscode .= "CreateSmallText('v', ". ($XStartPlan - 20) .", ". ($YOffset + 9 + 7) .", 'index.php?mod=seating&action=edit&step=4&blockid=". $_GET['blockid'] ."&change_sep_col=". ($y + 1) ."');\n";
                }
            }

            if (array_key_exists($y+1, $sep_cols) && $sep_cols[$y+1]) {
                $body[$y]['height'] = 28;
                $body[$y]['icon'] = "design/{$auth['design']}/images/arrows_seating_remove_sep_ver.gif";
            } else {
                $body[$y]['height'] = 14;
                $body[$y]['icon'] = "design/{$auth['design']}/images/arrows_seating_add_sep_ver.gif";
            }
            $actionParameter = $_GET['action'] ?? 'show';
            $body[$y]['link'] = "index.php?mod=seating&action={$actionParameter}&step=4&blockid=$blockid&change_sep_col=".($y + 1);

            $templ['seat']['cols'] = "";
            $sepX = 0;
            for ($x = 0; $x <= $block['cols']; $x++) {
                if (array_key_exists($x, $sep_rows) && $sep_rows[$x]) {
                    $sepX++;
                }
                $XOffset = $x * 14 + $sepX * 7 + $XStartPlan;

                switch ($mode) {
                    // Show plan
                    default:
                        $templ['seat']['cell_nr'] = $cell_nr;

                        if ($y == 1) {
                            $jscode .= "CreateText('". $this->CoordinateToName($x + 1, -1, $block['orientation']) ."', ". ($XOffset - 2) .", ". ($YStartPlan - 6) .", '');\n";
                        }
                        if ($y == 1 and $mode == 1) {
                            if (array_key_exists($x+1, $sep_rows) && $sep_rows[$x+1]) {
                                $jscode .= "CreateSmallText('<', ". ($XOffset - 2 + 9) .", ". ($YStartPlan - 16) .", 'index.php?mod=seating&action=edit&step=4&blockid=". $_GET['blockid'] ."&change_sep_row=". ($x + 1) ."');\n";
                            } else {
                                $jscode .= "CreateSmallText('>', ". ($XOffset - 2 + 9) .", ". ($YStartPlan - 16) .", 'index.php?mod=seating&action=edit&step=4&blockid=". $_GET['blockid'] ."&change_sep_row=". ($x + 1) ."');\n";
                            }
                        }

                        // Set seat link target
                        $link = '';
                        $seatStateValue = $seat_state[$y][$x] ?? 0;
                        switch ($mode) {
                            default:
                                if ($linktarget) {
                                    $link = "$linktarget&row=$y&col=$x";
                                } elseif ($auth['login']) {
                                    // If free and user has not paid -> Possibility to mark this seat
                                    if ($seatStateValue == 1 && (!$user_paid || !$user_paid['paid'])) {
                                        $link = "index.php?mod=seating&action=show&step=12&blockid=$blockid&row=$y&col=$x";
                                    // If free, or marked for another one -> Possibility to reserve this seat
                                    } elseif ($seatStateValue == 1 || ($seatStateValue == 3 && $seat_userid[$y][$x] != $auth['userid'])) {
                                        $link = "index.php?mod=seating&action=show&step=10&blockid=$blockid&row=$y&col=$x";
                                    // If assigned to me, or marked for me -> Possibility to free this seat again
                                    } elseif (($seatStateValue == 2 || $seatStateValue == 3) && $seat_userid[$y][$x] == $auth['userid']) {
                                        $link = "index.php?mod=seating&action=show&step=20&blockid=$blockid&row=$y&col=$x";
                                    // If assigned and user is admin -> Possibility to free this seat
                                    } elseif ($seatStateValue == 2 && $auth['type'] > \LS_AUTH_TYPE_USER) {
                                        #$link = "index.php?mod=seating&action=show&step=30&blockid=$blockid&row=$y&col=$x";
                                    }
                                }
                                break;
                            case 1:
                                break;
                            case 2:
                                // Seat only changeble, if noone sits there
                                if ($seatStateValue > 1 && $seatStateValue < 7) {
                                    $link = "javascript:alert(\"". t('Es können nur freie Sitzplätze geändert werden') ."\")";
                                } else {
                                    $link = "javascript:ChangeSeatingPlan(\"cell". ($x * 100 + $y) ."\", $XOffset, $YOffset)";
                                }
                                break;
                        }

                        // Generate popup
                        $seatStateValue = $seat_state[$y][$x] ?? 0;
                        if ($seatStateValue == 2 && $seat_userid[$y][$x] == $auth['userid']) {
                            $s_state = 8;
                        } elseif ($seatStateValue == 2 && in_array($seat_userid[$y][$x], $my_clanmates)) {
                            $s_state = 9;
                        } else {
                            $s_state = $seatStateValue;
                        }

                        $seatIP = $seat_ip[$y][$x] ?? '';
                        if ($seatIP == '') {
                            $seat_ip[$y][$x] = '<i>'. t('Keine zugeordnet') .'</i>';
                        }
                        $tooltip = '';
                        switch ($s_state) {
                            case "2":
                            case "3":
                            case "8":
                            case "9":
                                $tooltip .= t('Block') .': '. $this->CoordinateToBlockAndName($x + 1, $y, $blockid) . HTML_NEWLINE;
                                $tooltip .= t('Benutzername') .': '. $user_info[$y][$x]['username'] . HTML_NEWLINE;
                                if (
                                    !$cfg['sys_internet']
                                    || $auth['type'] > \LS_AUTH_TYPE_USER
                                    || (
                                        $selected_user
                                        && $auth['userid'] == $user_info[$y][$x]['userid']
                                        )
                                    ) {
                                        $tooltip .= t('Name') .': '. trim($user_info[$y][$x]['firstname']) .' '. trim($user_info[$y][$x]['name']) . HTML_NEWLINE;
                                    }
                                $tooltip .= t('Clan') .': '. $user_info[$y][$x]['clan'] . HTML_NEWLINE;
                                $tooltip .= t('IP') .': '. $seat_ip[$y][$x] . HTML_NEWLINE;
                                if (
                                    $func->chk_img_path($user_info[$y][$x]['avatar_path'])
                                    && (
                                        $cfg['seating_show_user_pics']
                                        || !$cfg['sys_internet']
                                        || $auth['type'] > \LS_AUTH_TYPE_USER
                                        || (
                                            $selected_user
                                            && $auth['userid'] == $user_info[$y][$x]['userid']
                                            )
                                        )
                                    ) {
                                      $tooltip .= '<img src=\''. $user_info[$y][$x]['avatar_path'] .'\' style=\'max-width:100%;\' />' . HTML_NEWLINE;
                                }
                                break;
                            case "1":
                                  $tooltip .= t('Block') .': '. $this->CoordinateToBlockAndName($x + 1, $y, $blockid) .' '. t('Frei'). HTML_NEWLINE;
                                  $tooltip .= t('IP') .': '. $seat_ip[$y][$x] . HTML_NEWLINE;
                                break;
                            case "7":
                                  $tooltip .= t('Block') .': '. $this->CoordinateToBlockAndName($x + 1, $y, $blockid) .' '. t('Gesperrt'). HTML_NEWLINE;
                                  $tooltip .= t('IP') .': '. $seat_ip[$y][$x] . HTML_NEWLINE;
                                break;
                            case "80":
                            case "81":
                                  $tooltip .= t('Block') .': '. $this->CoordinateToBlockAndName($x + 1, $y, $blockid) . HTML_NEWLINE;
                                  $tooltip .= t('Beschreibung') .': '. t('WC') . HTML_NEWLINE;
                                break;
                            case "82":
                                  $tooltip .= t('Block') .': '. $this->CoordinateToBlockAndName($x + 1, $y, $blockid) . HTML_NEWLINE;
                                  $tooltip .= t('Beschreibung') .': '. t('Notausgang') . HTML_NEWLINE;
                                break;
                            case "83":
                                  $tooltip .= t('Block') .': '. $this->CoordinateToBlockAndName($x + 1, $y, $blockid) . HTML_NEWLINE;
                                  $tooltip .= t('Beschreibung') .': '. t('Catering') . HTML_NEWLINE;
                                break;
                        }
                        $tooltip = addslashes(addslashes($tooltip));

                        // Set seat image
                        $body[$y]['line'][$x]['img_name'] = '';

                        $seatState = $seat_state[$y][$x] ?? null;
                        switch ($seatState) {
                            case 0:
                            case 100:
                                if ($mode == 1) {
                                    $jscode .= "DrawSeatingSymbol(0, $XOffset, $YOffset, '$link', '$tooltip');\n";
                                } elseif ($mode == 2) {
                                    $jscode .= "ClearArea($XOffset, $YOffset, 14, 14, '$link');\n";
                                }
                                break;
                            case 2:
                                if ($selected_user) {
                                    $userid = $selected_user;
                                } else {
                                    $userid = $auth['userid'];
                                }

                                if ($seat_userid[$y][$x] == $userid) {
                                    $seat_state[$y][$x] = 4;
                                // My Seat
                                } elseif (in_array($seat_userid[$y][$x], $my_clanmates)) {
                                    $seat_state[$y][$x] = 5;
                                // Clanmate
                                } elseif ($seat_user_checkout[$y][$x] and $seat_user_checkout[$y][$x] != '0000-00-00 00:00:00') {
                                    $seat_state[$y][$x] = 6;
                                    // Checked out
                                } elseif ($seat_user_checkin[$y][$x] and $seat_user_checkin[$y][$x] != '0000-00-00 00:00:00') {
                                    $seat_state[$y][$x] = 8;
                                } // Checked in
                                    // else = 2 -> Normal occupied seat

                            // No Break!
                            default:
                                if ($mode == 2) {
                                      $jscode .= "ClearArea($XOffset, $YOffset, 14, 14, '$link');\n";
                                }
                                $jscode .= "DrawSeatingSymbol({$seat_state[$y][$x]}, $XOffset, $YOffset, '$link', '$tooltip');\n";
                                break;
                        }

                        $templ['seat']['cell_content'] = '';
                        break;

                    // IP-Input-Fields
                    case 3:
                        $seatState = $seat_state[$y][$x] ?? 0;
                        if ($seatState >= 1 && $seatState < 10) {
                            $body[$y]['line'][$x]['content'] = "<input type=\"text\" name=\"cell[". ($x * 100 + $y) ."]\" size=\"15\" maxlength=\"15\" value=\"". $seat_ip[$y][$x] ."\" />";
                        } else {
                            $body[$y]['line'][$x]['content'] = "&nbsp;";
                        }
                        break;
                }
                $cell_nr++;
            }
        }

        if ($mode == 3) {
            $smarty->assign('body', $body);
        }
        $plan = $smarty->fetch('modules/seating/templates/plan.htm');

        if ($mode == 0) {
            $jscode .= "DrawSeatingSymbol(1, 0, ". ($YOffset + 50) .", '', '');\n";
            $jscode .= "CreateText('". t('Frei') ."', ". 14 .", ". ($YOffset + 58) .", '');\n";
            $jscode .= "DrawSeatingSymbol(2, 0, ". ($YOffset + 64) .", '', '');\n";
            $jscode .= "CreateText('". t('Besetzt') ."', ". 14 .", ". ($YOffset + 72) .", '');\n";
            $jscode .= "DrawSeatingSymbol(4, 0, ". ($YOffset + 78) .", '', '');\n";
            if ($selected_user) {
                $jscode .= "CreateText('". t('Auswahl') ."', ". 14 .", ". ($YOffset + 86) .", '');\n";
            } else {
                $jscode .= "CreateText('". t('Dein Platz') ."', ". 14 .", ". ($YOffset + 86) .", '');\n";
            }
            $jscode .= "DrawSeatingSymbol(3, 0, ". ($YOffset + 92) .", '', '');\n";
            $jscode .= "CreateText('". t('Vorgemerkt') ."', ". 14 .", ". ($YOffset + 100) .", '');\n";

            $jscode .= "DrawSeatingSymbol(6, 100, ". ($YOffset + 50) .", '', '');\n";
            $jscode .= "CreateText('". t('Frei (Ausgecheckt)') ."', ". 114 .", ". ($YOffset + 58) .", '');\n";
            $jscode .= "DrawSeatingSymbol(8, 100, ". ($YOffset + 64) .", '', '');\n";
            $jscode .= "CreateText('". t('Besetzt (Eingecheckt)') ."', ". 114 .", ". ($YOffset + 72) .", '');\n";
            $jscode .= "DrawSeatingSymbol(5, 100, ". ($YOffset + 78) .", '', '');\n";
            $jscode .= "CreateText('". t('Clanmate') ."', ". 114 .", ". ($YOffset + 86) .", '');\n";
            $jscode .= "DrawSeatingSymbol(7, 100, ". ($YOffset + 92) .", '', '');\n";
            $jscode .= "CreateText('". t('Gesperrt') ."', ". 114 .", ". ($YOffset + 100) .", '');\n";
        }

        if (isset($jscode) && is_string($jscode) && $jscode !== '') {
            $framework->addJavaScriptCode($jscode);
        }

        return $plan;
    }


    /**
     * @param int $userid
     * @return void
     */
    public function ReserveSeatIfPaidAndOnlyOneMarkedSeat($userid)
    {
        global $db, $database, $party;

        $res = $db->qry("
          SELECT
            s.seatid,
            s.status
          FROM %prefix%seat_block AS b
          LEFT JOIN %prefix%seat_seats AS s ON b.blockid = s.blockid
          WHERE
            b.party_id = %int%
            AND s.userid = %int%", $party->party_id, $userid);

        $row = $db->fetch_array($res);
        if ($db->num_rows($res) == 1 and $row['status'] == 3) {
            $database->query("UPDATE %prefix%seat_seats SET status = 2 WHERE seatid = ?", [$row['seatid']]);
        }
    }

    /**
     * @param int $userid
     * @return void
     */
    public function MarkSeatIfNotPaidAndSeatReserved($userid)
    {
        global $db, $database, $party;

        $row = $database->queryWithOnlyFirstRow("
          SELECT
            s.seatid,
            s.status
          FROM %prefix%seat_block AS b
          LEFT JOIN %prefix%seat_seats AS s ON b.blockid = s.blockid
          WHERE
            b.party_id = ?
            AND s.userid = ?
            AND s.status = 2", [$party->party_id, $userid]);

        if (is_array($row) && $row['seatid'] > 0) {
            $database->query("UPDATE %prefix%seat_seats SET status = 3 WHERE seatid = ?", [$row['seatid']]);
        }
    }

    /**
     * @param int $userid
     * @param int t$blockid
     * @param int $row
     * @param int $col
     * @return void
     */
    public function AssignSeat($userid, $blockid, $row, $col)
    {
        global $database, $party;

        // Delete old seat, if exists
        $my_party_seat = $database->queryWithOnlyFirstRow("
          SELECT
            s.seatid
          FROM %prefix%seat_block AS b
          LEFT JOIN %prefix%seat_seats AS s ON b.blockid = s.blockid
          WHERE
            b.party_id = ?
            AND s.userid = ?
            AND status = 2", [$party->party_id, $userid]);

        if (is_array($my_party_seat) && $my_party_seat['seatid']) {
            $database->query("UPDATE %prefix%seat_seats SET userid = 0, status = 1 WHERE seatid = ?", [$my_party_seat['seatid']]);
        }

        // Assign new seat
        $database->query("
          UPDATE %prefix%seat_seats
          SET
            userid = ?,
            status = 2
          WHERE
            blockid = ?
            AND `row` = ?
            AND col = ?", [$userid, $blockid, $row, $col]);
    }

    /**
     * @param int $userid
     * @param int $blockid
     * @param int $row
     * @param int $col
     * @return void
     */
    public function MarkSeat($userid, $blockid, $row, $col)
    {
        global $db, $database;

        $db->qry("
          UPDATE %prefix%seat_seats
          SET
            userid = %int%,
            status = 3
          WHERE
            blockid = %int%
            AND `row` = %string%
            AND col = %string%", $userid, $blockid, $row, $col);
    }

    /**
     * @param int $blockid
     * @param int $row
     * @param int $col
     * @return void
     */
    public function FreeSeat($blockid, $row, $col)
    {
        global $db, $database;

        $db->qry("
          UPDATE %prefix%seat_seats
          SET
            userid = 0,
            status = 1
          WHERE
            blockid = %int%
            AND `row` = %string%
            AND col = %string%", $blockid, $row, $col);
    }

    /**
     * @param int $userid
     * @return void
     */
    public function FreeSeatAllMarkedByUser($userid)
    {
        global $db, $database, $party;

        $db->qry("
          UPDATE %prefix%seat_seats AS s
          LEFT JOIN %prefix%seat_block AS b ON b.blockid = s.blockid
          SET
            s.userid = 0,
            s.status = 1
          WHERE
            s.userid = %int%
            AND b.party_id = %int% ", $userid, $party->party_id);
    }

    /**
     * Store a floor plan upload and its alignment for one block.
     */
    public function applyFloorplanFromRequest(int $blockid): void
    {
        global $database, $func;

        $block = $database->queryWithOnlyFirstRow(
            "SELECT floorplan, floorplan_x, floorplan_y, floorplan_scale FROM %prefix%seat_block WHERE blockid = ?",
            [$blockid]
        );
        $current = is_array($block) ? (string)($block['floorplan'] ?? '') : '';
        $uploaded = isset($_FILES['floorplan']) && (int)($_FILES['floorplan']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

        if (!$uploaded && !empty($_POST['floorplan_remove'])) {
            $this->deleteStoredFloorplan($current);
            $current = '';
        }

        if ($uploaded) {
            $extension = $this->floorplanExtension((string)$_FILES['floorplan']['tmp_name']);
            if ($extension !== '') {
                $directory = 'ext_inc/seating';
                if (!is_dir($directory)) {
                    $func->CreateDir($directory);
                }
                $target = $directory . '/block' . $blockid . '.' . $extension;
                if (is_file($target)) {
                    unlink($target);
                }
                $stored = $func->FileUpload('floorplan', $directory, 'block' . $blockid . '.' . $extension);
                if (is_string($stored) && $stored !== '' && is_file($stored)) {
                    if ($current !== '' && $current !== $stored) {
                        $this->deleteStoredFloorplan($current);
                    }
                    $current = str_replace('\\', '/', $stored);
                }
            }
        }

        if (isset($_POST['floorplan_scale'])) {
            [$x, $y, $scale] = $this->floorplanAlignmentFromRequest();
        } else {
            $x = is_array($block) ? (int)($block['floorplan_x'] ?? 0) : 0;
            $y = is_array($block) ? (int)($block['floorplan_y'] ?? 0) : 0;
            $scale = is_array($block) ? (int)($block['floorplan_scale'] ?? 100) : 100;
            if ($scale < 10 || $scale > 400) {
                $scale = 100;
            }
        }
        $database->query(
            "UPDATE %prefix%seat_block SET floorplan = ?, floorplan_x = ?, floorplan_y = ?, floorplan_scale = ? WHERE blockid = ?",
            [$current, $x, $y, $scale, $blockid]
        );
    }

    /**
     * Store one pixel width for each active aisle. Does not change the floor-plan scale.
     */
    public function saveAisleWidths(int $blockid): void
    {
        global $database;

        $posted = $_POST['aisle_width'] ?? null;
        if (!is_array($posted)) {
            return;
        }
        foreach ($posted as $orientation => $widths) {
            $orientation = (string)$orientation;
            if (!in_array($orientation, ['0', '1'], true) || !is_array($widths)) {
                continue;
            }
            foreach ($widths as $value => $width) {
                $width = max(4, min(400, (int)$width));
                $database->query(
                    "UPDATE %prefix%seat_sep SET width = ? WHERE blockid = ? AND orientation = ? AND value = ?",
                    [$width, $blockid, $orientation, (int)$value]
                );
            }
        }
    }

    /**
     * Update only offset and scale. Used by the floor-plan step.
     */
    public function saveFloorplanAlignment(int $blockid): void
    {
        global $database;

        if (!isset($_POST['floorplan_scale'])) {
            return;
        }

        [$x, $y, $scale] = $this->floorplanAlignmentFromRequest();
        $database->query(
            "UPDATE %prefix%seat_block SET floorplan_x = ?, floorplan_y = ?, floorplan_scale = ? WHERE blockid = ?",
            [$x, $y, $scale, $blockid]
        );
    }

    public function deleteStoredFloorplan(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        if (!str_starts_with($path, 'ext_inc/seating/') || str_contains($path, '..')) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function floorplanAlignmentFromRequest(): array
    {
        $x = max(-4000, min(4000, (int)($_POST['floorplan_x'] ?? 0)));
        $y = max(-4000, min(4000, (int)($_POST['floorplan_y'] ?? 0)));
        $scale = (int)($_POST['floorplan_scale'] ?? 100);
        if ($scale < 10 || $scale > 400) {
            $scale = 100;
        }

        return [$x, $y, $scale];
    }

    private function floorplanExtension(string $tmpName): string
    {
        $info = @getimagesize($tmpName);
        $type = is_array($info) ? (int)($info[2] ?? 0) : 0;
        return match ($type) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => '',
        };
    }
}

<?php

/*
 * Ultiranks pool standings. Served instead of poolstatus.php by the skin view
 * override in index.php. A division opens on its initial pools with one button
 * per stage, and round-robin and Swiss-draw pools show only a compact
 * standings table with team logos. Everything this page does not redesign
 * (the event-wide list, printable version, brackets and cross matches) is left
 * to the standard page.
 */

require_once __DIR__ . '/../../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);
require_once __DIR__ . '/../../../lib/view.guard.php';
requireRoutedView('poolstatus');

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$standardPage = __DIR__ . '/../../../poolstatus.php';

$seriesId = 0;
$currentPool = 0;
$poolinfo = null;
if (iget("pool") && !iget("season") && !iget("series")) {
    $poolinfo = PoolInfo(iget("pool"));
    if ($poolinfo) {
        $seriesId = (int) $poolinfo['series'];
        $currentPool = (int) $poolinfo['pool_id'];
    }
} elseif (iget("series") && !iget("season")) {
    $seriesinfo = SeriesInfo(iget("series"));
    if ($seriesinfo) {
        $seriesId = (int) $seriesinfo['series_id'];
    }
}

// The standard page also redirects a one-game round robin to its game.
$delegate = !$seriesId || iget("print");
if (!$delegate && $poolinfo && $poolinfo['type'] == 1 && count(PoolGames($currentPool)) == 1) {
    $delegate = true;
}

if (!$delegate) {
    $seriesinfo = SeriesInfo($seriesId);
    $seasoninfo = SeasonInfo($seriesinfo['season']);
    $initialPools = SeriesPools($seriesId, true, true);
    $initialIds = array_map('intval', array_column($initialPools, 'pool_id'));

    if ($currentPool) {
        $pools = [["pool_id" => $currentPool]];
    } elseif (count($initialPools)) {
        $pools = $initialPools;
    } else {
        $pools = SeriesPools($seriesId, true);
    }
    foreach ($pools as $pool) {
        $type = (int) PoolInfo($pool['pool_id'])['type'];
        if ($type !== 1 && $type !== 3) {
            $delegate = true;
            break;
        }
    }
}

if ($delegate) {
    include $standardPage;
    return;
}

$title = _("Pool standings") . " " . U_($seriesinfo['name']);
if ($currentPool) {
    $title .= ", " . U_($poolinfo['name']);
}

$html = "";
if (!$currentPool) {
    $html .= CommentHTML(2, $seriesId);
}
$html .= SeasonBannerHTML($seasoninfo['season_id']);
$html .= "<h1>" . utf8entities(U_($seriesinfo['name'])) . "</h1>\n";
$html .= urStageNav($seriesId, $initialIds, SeriesPools($seriesId, true), $currentPool);

foreach ($pools as $pool) {
    $info = PoolInfo($pool['pool_id']);
    $html .= "<h2>" . utf8entities(U_($info['name'])) . "</h2>";
    $html .= CommentHTML(3, $info['pool_id']);
    if ($info['type'] == 1) {
        $html .= urRoundRobinTable($seasoninfo, $info);
    } else {
        $html .= urSwissdrawTable($seasoninfo, $info);
    }
}

$querystring = preg_replace('/(&|^)print=[^&]*/i', '', $_SERVER['QUERY_STRING']);
$querystring = ltrim($querystring, '&');
$html .= "<hr/>";
$html .= "<div style='text-align:right'><a href='?" . utf8entities($querystring) . "&amp;print=1'>" . _("Printable version") . "</a></div>";

showPage($title, $html);

/**
 * One button for the initial pools, then one for each visible continuing pool.
 * Returns nothing when there is only one stage to show.
 */
function urStageNav($seriesId, $initialIds, $visiblePools, $currentPool)
{
    $onInitial = $currentPool === 0 || in_array($currentPool, $initialIds, true);
    $links = [];
    if (count($initialIds)) {
        $links[] = urStageLink("?view=poolstatus&amp;series=" . (int) $seriesId, _("Initial pools"), $onInitial);
    }
    foreach ($visiblePools as $pool) {
        $poolId = (int) $pool['pool_id'];
        if (!in_array($poolId, $initialIds, true)) {
            $links[] = urStageLink("?view=poolstatus&amp;pool=" . $poolId, U_($pool['name']), $poolId === $currentPool);
        }
    }
    if (count($links) < 2) {
        return "";
    }
    return "<nav class='poolstages'>" . implode("", $links) . "</nav>\n";
}

function urStageLink($href, $label, $active)
{
    $current = $active ? " active' aria-current='page" : "";
    return "<a class='poolstage" . $current . "' href='" . $href . "'>" . utf8entities($label) . "</a>";
}

/**
 * Team name cell with a round logo slot: the team profile image, or the
 * team's initials when it has none. A team id of 0 is a not-yet-known team.
 */
function urTeamCell($teamId, $name, $flag)
{
    $teamId = (int) $teamId;
    $profile = $teamId ? TeamProfile($teamId) : null;
    if (!empty($profile['profile_image'])) {
        $logo = "<span class='teamlogo'><img src='" . UPLOAD_DIR . "teams/" . $teamId . "/thumbs/" . utf8entities($profile['profile_image']) . "' alt='' loading='lazy'/></span>";
    } else {
        $initials = "";
        foreach (preg_split('/\s+/u', trim((string) $name)) as $word) {
            if ($word !== "" && mb_strlen($initials) < 2) {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }
        $logo = "<span class='teamlogo teamlogo-empty' aria-hidden='true'>" . ($teamId ? utf8entities($initials) : "") . "</span>";
    }
    $label = utf8entities($name);
    if ($teamId) {
        $label = "<a href='?view=teamcard&amp;team=" . $teamId . "'>" . $label . "</a>";
    }
    return "<td><div class='teamcell'>" . $logo . $flag . $label . "</div></td>";
}

function urFlag($seasoninfo, $flagfile)
{
    if (!intval($seasoninfo['isinternational'])) {
        return "";
    }
    return "<img height='10' src='images/flags/tiny/" . $flagfile . "' alt=''/> ";
}

function urMoveColor($movetopool)
{
    if (!$movetopool) {
        return "";
    }
    return "background-color:#" . $movetopool['color'] . ";background-color:" . RGBtoRGBa($movetopool['color'], 0.3) . ";color:#" . textColor($movetopool['color']);
}

function urRoundRobinTable($seasoninfo, $poolinfo)
{
    $style = $poolinfo['played'] ? "style='font-weight: bold;'" : "";
    $columns = ['games', 'wins'];
    if ($poolinfo['drawsallowed']) {
        $columns[] = 'draws';
    }
    $columns = array_merge($columns, ['losses', 'goalsfor', 'goalsagainst', 'goalsdiff']);

    $ret = "<table $style border='2' width='100%'>\n";
    $ret .= ColumnLegend($columns);
    $ret .= "<tr><th>#</th><th style='width:200px'>" . _("Team") . "</th>";
    foreach ($columns as $column) {
        $ret .= ColumnAbbrCell($column);
    }
    $ret .= "</tr>\n";

    $poolId = $poolinfo['pool_id'];
    $standings = PoolTeams($poolId, "rank");
    $teams = PoolSchedulingTeams($poolId);
    $continuationpools = [];
    $gamesplayed = PoolTotalPlayedGames($poolId);

    $i = 1;
    if (!$poolinfo['continuingpool'] || count($standings) >= count($teams)) {
        foreach ($standings as $row) {
            $stats = TeamStatsByPool($poolId, $row['team_id']);
            $points = TeamPointsByPool($poolId, $row['team_id']);
            $movetopool = PoolGetMoveToPool($poolId, $i);
            if ($movetopool) {
                $continuationpools[] = $movetopool;
            }
            $rank = $gamesplayed > 0 ? $row['activerank'] : "-";
            $ret .= "<tr><td class='center'><div style='" . urMoveColor($movetopool) . "'>" . $rank . "</div></td>";
            $ret .= urTeamCell($row['team_id'], $row['name'], urFlag($seasoninfo, $row['flagfile']));
            $cells = [$stats['games'], $stats['wins']];
            if ($poolinfo['drawsallowed']) {
                $cells[] = $stats['draws'];
            }
            $cells[] = $stats['losses'];
            $cells[] = $points['scores'];
            $cells[] = $points['against'];
            $cells[] = intval($points['scores']) - intval($points['against']);
            foreach ($cells as $value) {
                $ret .= "<td class='center'><div>" . intval($value) . "</div></td>";
            }
            $ret .= "</tr>\n";
            $i++;
        }
    } else {
        foreach ($teams as $row) {
            $realteam = PoolTeamFromStandings($poolId, $i);
            $movetopool = PoolGetMoveToPool($poolId, $i);
            if ($movetopool) {
                $continuationpools[] = $movetopool;
            }
            $ret .= "<tr><td class='center' style='" . urMoveColor($movetopool) . "'>-</td>";
            if ($realteam) {
                $ret .= urTeamCell($realteam['team_id'], $realteam['name'], urFlag($seasoninfo, $realteam['flagfile']));
            } else {
                $ret .= urTeamCell(0, U_($row['name']), "");
            }
            $ret .= str_repeat("<td class='center'>-</td>", count($columns));
            $ret .= "</tr>\n";
            $i++;
        }
    }
    $ret .= "</table>\n";

    if (count($continuationpools)) {
        $ret .= "<table width='100%'><tr>\n";
        $prev = "";
        $width = 100 / count($continuationpools);
        foreach ($continuationpools as $cpool) {
            if ($cpool['topool'] != $prev) {
                $ret .= "<td style='" . urMoveColor($cpool) . ";width:" . $width . "%'>";
                if ($cpool['visible']) {
                    $ret .= "<a href='?view=poolstatus&amp;pool=" . $cpool['topool'] . "'>" . utf8entities(U_($cpool['name'])) . "</a>";
                } else {
                    $ret .= utf8entities(U_($cpool['name']));
                }
                $ret .= "</td>";
                $prev = $cpool['topool'];
            }
        }
        $ret .= "</tr></table>\n";
    }
    return $ret;
}

function urSwissdrawTable($seasoninfo, $poolinfo)
{
    $style = $poolinfo['played'] ? "class='playedpool'" : "";
    $ret = "<table $style border='2' width='100%'>\n";
    $ret .= "<tr><th>#</th><th style='width:200px'>" . _("Team") . "</th>";
    $ret .= "<th class='center'>" . _("Games") . "</th>";
    $ret .= "<th class='center'>" . _("Victory Points") . "</th>";
    $ret .= "<th class='center'>" . _("Opponent VPs") . "</th>";
    $ret .= "<th class='center'>" . _("Margin") . "</th>";
    $ret .= "<th class='center'>" . _("Goals") . "</th>";
    $ret .= "</tr>\n";

    $standings = PoolTeams($poolinfo['pool_id'], "rank");
    if (count($standings)) {
        foreach ($standings as $row) {
            $vp = TeamVictoryPointsByPool($poolinfo['pool_id'], $row['team_id']);
            $ret .= "<tr><td>" . $row['activerank'] . "</td>";
            $ret .= urTeamCell($row['team_id'], U_($row['name']), urFlag($seasoninfo, $row['flagfile']));
            foreach (['games', 'victorypoints', 'oppvp', 'margin', 'score'] as $key) {
                $ret .= "<td class='center'>" . intval($vp[$key]) . "</td>";
            }
            $ret .= "</tr>\n";
        }
    } else {
        foreach (PoolSchedulingTeams($poolinfo['pool_id']) as $row) {
            $ret .= "<tr><td>-</td>";
            $ret .= urTeamCell(0, U_($row['name']), "");
            $ret .= str_repeat("<td class='center'>-</td>", 5);
            $ret .= "</tr>\n";
        }
    }
    $ret .= "</table>\n";
    return $ret;
}

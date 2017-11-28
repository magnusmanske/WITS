<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR); // E_ALL|
ini_set('display_errors', 'On');
require_once ( '/data/project/wits/wits.php' ) ;
require_once ( '/data/project/wits/public_html/php/wikidata.php' ) ;
require_once ( '/data/project/wits/public_html/php/tt.php' ) ;

$tt = new ToolTranslation ( array ( 'tool' => 'wits' , 'highlight_missing' => true ) ) ;

$catalog = get_request ( 'catalog' , 0 ) * 1 ;
$download = get_request ( 'download' , '' ) ;

function getItemBox ( $q , $note = '' ) {
	global $lad ;
	$h = '' ;
	$label = $q ;
	if ( isset($lad['label'][$q]) ) $label = $lad['label'][$q] ;
	$h .= "<div class='list-group-item list-group-item-action flex-column align-items-start' style='overflow:auto'>" ;

	$h .= '<div class="d-flex w-100 justify-content-between">' ;
	if ( isset($lad['images'][$q]) ) {
		$img = $lad['images'][$q] ;
		$url = "https://commons.wikimedia.org/wiki/Special:Redirect/file/" . myurlencode($img) . "?width=120px" ;
		$h .= "<div style='float:right'><a href='https://commons.wikimedia.org/wiki/File:".myurlencode($img)."' target='_blank'><img border=0 src='$url' class='img-thumbnail' style='max-height:120px'></a></div>" ;
	}
	$h .= "<h5 class='mb-1'><a href='https://www.wikidata.org/wiki/$q' target='_blank'>$label</a>" ;
	if ( $label != $q ) $h .= " <small class='text-muted'>$q</small>" ;
	$h .= "</h5>" ;
	$h .= '</div>' ;
	
	if ( isset($lad['description'][$q]) ) $h .= "<p class='mb-1'>{$lad['description'][$q]}</p>" ;
	
	if ( $note != '' ) $h .= "<small>$note</small>" ;

	$h .= '</div>' ;
	return $h ;
}

// This assumes that all items are of the form /^Q\d+$/
function loadItemLabelsAndDescriptions ( &$items ) {
	global $tt ;
	$ret = [ 'label' => [] , 'description' => [] , 'images' => [] ] ;
	if ( count($items) == 0 ) return $ret ;

	$languages = ['en','de','fr','es','it','nl','ru','zh'] ;
	$l = $tt->getLanguage() ;
	$languages = array_diff ( $languages , [$l] ) ; // Remove from list, if exists
	array_unshift ( $languages , $l ) ;
	$db = openDB ( 'wikidata' , 'wikidata' ) ;
	
	foreach ( ['label','description'] AS $type ) {
		$itl = $items ;
		foreach ( $languages AS $l ) {
			$sql = "SELECT term_full_entity_id,term_text FROM wb_terms WHERE term_entity_type='item' AND term_language='$l' AND term_type='$type' AND term_full_entity_id IN ('" . implode ( "','" , $itl ) . "')" ;
			if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
			while($o = $result->fetch_object()){
				unset ( $itl[$o->term_full_entity_id] ) ;
				if ( !isset($ret[$type][$o->term_full_entity_id]) ) $ret[$type][$o->term_full_entity_id] = $o->term_text ;
			}
			if ( count($itl) == 0 ) break ;
		}
	}
	
	$sql = "SELECT page_title,pp_value FROM page,page_props WHERE page_id=pp_page and page_namespace=0 AND page_title IN ('" . implode ( "','" , $items ) . "') AND pp_propname='page_image_free'" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()) $ret['images'][$o->page_title] = $o->pp_value ;
	
	return $ret ;
}

function compare_edits ( $a , $b ) {
	if ( $a < $b ) return 1 ;
	if ( $a > $b ) return -1 ;
	return 0 ;
}

function compare_users ( $a , $b ) {
	if ( $a->edits > $b->edits ) return -1 ;
	if ( $a->edits < $b->edits ) return 1 ;
	return 0 ;
}

function expandBox ( $num , $title , $box_contents ) {
	$h = '<tr>' ;
	$h .= "<td class='right_num' nowrap>" ;
	if ( $num == '0' ) $h .= '0' ;
	else if ( $num != '' ) $h .= number_format($num) ;
	$h .= "</td>" ;
	$h .= "<td style='width:100%'>" ;
	$h .= "<div class='mybox'>" ;
	$h .= "<div class='mybox_header'>" ;
	$h .= "<div class='mybox_title'>" . trim($title) . "</div>" ;
	if ( $box_contents != '' ) {
		$h .= "<div class='mybox_expander'> [<a href='#' class='mybox_show'>+</a><a href='#' class='mybox_hide'>&ndash;</a></div>]</div>" ;
		$h .= "<div class='mybox_contents'>$box_contents</div>" ;
	} else $h .= "</div>" ;
	$h .= "</div>" ;
	$h .= "</td></tr>" ;
	return $h ;
}


$wits = new WITS ( $catalog ) ;
//$db = openToolDB ( 'wits_p' ) ;

print get_common_header ( '' , $tt->t('wits') ) ;
print $tt->getScriptTag() ;
print $tt->getJS('#tooltranslate_wrapper') ;
?>

<style>
</style>

<script>
$('#discuss_link').text('Manual').attr({href:'https://www.wikidata.org/wiki/Wikidata:WITS'}) ;
$('#git').attr({href:'https://github.com/magnusmanske/WITS'}) ;
</script>

<?PHP
if ( $catalog == 0 ) {
	$catalogs = $wits->getCatalogs() ;
//	print "<pre>" ; print_r ( $catalogs ) ; print "</pre>" ;
//	exit ( 0 ) ;
	print "<h2>Catalogs</h2>" ;
	print "<table class='table table-condensed table-striped'>" ;
	print "<thead><tr><th>Statistics</th><th>Catalog</th><th>Owner</th><th>#months</th><th>Download</th></tr></thead>" ;
	print "<tbody>" ;
	foreach ( $catalogs AS $o ) {
		print "<tr>" ;
		print "<td nowrap><a href='?catalog={$o->id}'>view</a></td>" ;
		print "<td style='width:100%'>" . $wits->getLinkedName($o) . "</td>" ;
		print "<td nowrap><a class='wikidata' href='//www.wikidata.org/wiki/" . myurlencode($o->owner) . "' target='_blank'>{$o->owner}</a></td>" ;
//		print "<td>{$o->wdq}</td>" ;
		print "<td nowrap style='text-align:right;font-family:Courier'>" . number_format($o->stats_count) . "</td>" ;
		print "<td nowrap>" ;
		print "<a href='?catalog={$o->id}&download=full'>Full</a>" ;
		print "</td>" ;
		print "</tr>" ;
	}
	print "</tbody></table>" ;
	print get_common_footer() ;


} else if ( $catalog > 0 ) {
	$wits->loadCatalogStats() ;
	
	$lad = loadItemLabelsAndDescriptions ( $wits->all_items ) ;
	
?>

<script type="text/javascript" src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>


<style>
.right_num {
	font-family:Courier;
	text-align:right;
}
div.mybox_title {
	display:inline;
}
div.mybox_expander {
	display:inline;
}
div.mybox_contents {
	display:none;
	margin-left:10px;
}
a.mybox_hide {
	display:none;
}
.plot_description {
	font-size:9pt;
}
</style>

<h2 tt='catalog_details'></h2>
<table class='table'>
<tbody>
<tr><th nowrap tt='catalog_id'></th><td style='width:100%'><?PHP print $wits->catalog->id ?></td></tr>
<tr><th nowrap tt='catalog_name'></th><td><?PHP print $wits->getLinkedName() ?></td></tr>
<tr><th nowrap tt='owner'></th><td><a class='wikidata' href='https://www.wikidata.org/wiki/User:<?PHP print myurlencode($wits->catalog->owner) ?>' target='_blank'><?PHP print escape_attribute($wits->catalog->owner) ?></a></td></tr>
<tr><th nowrap tt='months_in_db'></th><td><?PHP print number_format(count($wits->stats)) ?></td></tr> 
<tr><th nowrap>SPARQL</th><td><tt style='font-size:10pt;font-family:Courier'><?PHP print escape_attribute($wits->catalog->sparql) ?></tt>
<br/>
[<a class='external' href='https://query.wikidata.org/#<?PHP print escape_attribute($wits->catalog->sparql) ?>' target='_blank' tt='in_query_engine'></a>]</td></tr>
</tbody>
</table>

<h2 tt='changes_over_time'></h2>
<h3 tt='items'></h3>
<p class='plot_description' tt='plot_description_items'>&nbsp;</p>
<div id="flot3" style='width:100%;height:200px'></div>
<h3 tt='edits'></h3>
<p class='plot_description' tt='plot_description_edits'>&nbsp;</p>
<div id="flot1" style='width:100%;height:200px'></div>
<h3 tt='actions'></h3>
<p class='plot_description' tt='plot_description_actions'>&nbsp;</p>
<div id="flot2" style='width:100%;height:400px'></div>


<?PHP

$flot1_data = [
	[ 'label' => '<span tt="ip"></span>' , 'data' => [] ] , // each data point [ 1-based-month-col-num , total-value ]
	[ 'label' => '<span tt="bot"></span>' , 'data' => [] ] ,
	[ 'label' => '<span tt="user"></span>' , 'data' => [] ]
] ;
$flot1_options = [
	'series' => [ 'stack' => 1 , 'lines' => [ 'show' => 0 , 'step' => 0 ] , 'bars' => [ 'show' => 1 , 'barWidth' => 0.9 , 'align' => 'center' , 'lineWidth' => 0 ] ] ,
	'legend' => [ 'position' => 'nw' ] ,
	'grid' => [ 'clickable' => 1 ] ,
	'xaxis' => [ 'ticks' => [] ] // tick = [ num , month-label ]
] ;

$flot3_data = [
	[ 'label' => '<span tt="sparql_only"></span>' , 'data' => [] ] ,
	[ 'label' => '<span tt="existing_at_beginning"></span>' , 'data' => [] ] ,
	[ 'label' => '<span tt="items_created_this_month"></span>' , 'data' => [] ]
] ;

$col2anchor = [] ;
$last_year = 0 ;
$action_data = [] ;
$type2row = [ 'anon' => 0 , 'bot' => 1 , 'user' => 2 ] ;
$tmp = array_reverse ( $wits->stats ) ;
foreach ( $tmp AS $stats_num => $stat ) {
	$label = ($stat->month>9?'':'0') . "{$stat->month}" ;
	if ( $stat->year != $last_year ) {
		$label = "$label<br/>" . $stat->year ;
		$last_year = $stat->year ;
	} 
	$col = count ( $flot1_options['xaxis']['ticks'] ) + 1 ;
	$col2anchor[$col] = 'month_' . $stat->year . '_' . $stat->month ;
	$flot1_options['xaxis']['ticks'][] = [ $col , $label ] ;
	$j = $stat->json ;
	
	if ( isset($j->items) and isset($j->items->do_not_exist_before) and isset($j->items->sparql) and isset($j->items->created) ) {
		$created = count($j->items->created) ;
		$exist_before = $j->items->sparql - $j->items->do_not_exist_before ;
		$sparql = $j->items->sparql - $exist_before - $created ;
		$flot3_data[0]['data'][] = [ $col , $sparql ] ;
		$flot3_data[1]['data'][] = [ $col , $exist_before ] ;
		$flot3_data[2]['data'][] = [ $col , $created ] ;
	}
	
	$col2user = [] ;
	
	foreach ( $j->edits AS $k => $cnt ) {
		$k = json_decode ( $k ) ;

		if ( isset($k->user) ) {
			$row = $type2row[$k->user] ;
			if ( isset($row) ) $col2user[$row][$col] += $cnt ;
		}
		
		if ( isset($k->action) ) {
//			if ( !isset($action_data[$k->action][$col]) ) $action_data[$k->action][$col] = 0 ;
			$action_data[$k->action][$col] += $cnt ;
		}
	}
	
	foreach ( $col2user AS $row => $d ) {
		foreach ( $d AS $col => $cnt ) $flot1_data[$row]['data'][] = [ $col , $cnt ] ;
	}
}

$flot2_data = [] ;
foreach ( $action_data AS $action => $v ) {
	$d = [] ;
	foreach ( $v AS $col => $cnt ) $d[] = [ $col , $cnt ] ;
	$label = $tt->t($action) ;
	if ( $label == '' ) $label = $action ;
	$flot2_data[] = [
		'label' => $label ,
		'data' => $d
	] ;
}

//print "<pre style='font-size:8pt'>" ; print_r ( $flot1_data ) ; print "</pre>" ;
//print "<pre style='font-size:8pt'>" ; print_r ( $flot1_options ) ; print "</pre>" ;

print "<script>
var flot1_data = " . json_encode ( $flot1_data ) . ";
var flot2_data = " . json_encode ( $flot2_data ) . ";
var flot3_data = " . json_encode ( $flot3_data ) . ";
var flot1_options = " . json_encode ( $flot1_options ) . ";
var col2anchor = " . json_encode ( $col2anchor ) . ";
" ;
?>

$(document).ready ( function () {
	$.plot($("#flot1"), flot1_data, flot1_options);
	$.plot($("#flot2"), flot2_data, flot1_options);
	$.plot($("#flot3"), flot3_data, flot1_options);

	$("#flot1").bind("plotclick", function (event, pos, item) {
		if ( item ) location.href = location.href.replace(/\#.*$/,'') + '#' + col2anchor[item.datapoint[0]] ;
	} ) ;
	$("#flot2").bind("plotclick", function (event, pos, item) {
		if ( item ) location.href = location.href.replace(/\#.*$/,'') + '#' + col2anchor[item.datapoint[0]] ;
	} ) ;
	$("#flot3").bind("plotclick", function (event, pos, item) {
		if ( item ) location.href = location.href.replace(/\#.*$/,'') + '#' + col2anchor[item.datapoint[0]] ;
	} ) ;

} ) ;
</script>
<?PHP

// Edit summary
print "<h2 tt='edit_summary'></h2>" ;

function getLinkedUserName ( $name , $type = '' ) {
	if ( $name == 'anonymous editor' and $type == 'IP' ) return $u->name ;
	return "<a class='wikidata' target='_blank' href='https://www.wikidata.org/wiki/User:" . myurlencode($name) . "'>" . $name . "</a>" ;
}

// Editor summary
$total_edits = 0 ;
$total_user_edits = [] ;
foreach ( $wits->stats AS $k => $v ) {
	$j = $v->json ;
	foreach ( $j->users AS $u ) {
		if ( !isset($total_user_edits[$u->name]) ) $total_user_edits[$u->name] = (object) [ 'type'=>$u->type , 'edits'=>0 ] ;
		$total_user_edits[$u->name]->edits += $u->edits ;
		$total_edits += $u->edits ;
	}
}
if ( count($total_user_edits) > 0 ) {
	print "<h3 tt='top_editors'></h3>" ;
	uasort ( $total_user_edits , 'compare_users' ) ;
	$cnt = 0 ;
	$edits_accounted_for = 0 ;
	print "<table class='table table-striped table-sm'><thead><tr><th tt='user'></th><th tt='type'></th><th tt='edits'></th></tr></thead><tbody>" ;
	foreach ( $total_user_edits AS $name => $u ) {
		$edits_accounted_for += $u->edits ;
		$cnt++ ;
		print "<tr><td>" . getLinkedUserName($name,$u->type) . "</td><td><span tt='{$u->type}'></span></td><td nowrap class='right_num'>" ;
		if ( $u->type == 'IP' ) print number_format($u->edits) ;
		else print "<a href='https://xtools.wmflabs.org/ec/www.wikidata.org/" . myurlencode($name) . "' target='_blank' class='external'>" . number_format($u->edits) . "</a>" ;
		print "</td></tr>" ;
		if ( $cnt >= 10 and $edits_accounted_for >= $total_edits*0.9 ) break ; // Top 10 editors, >=90% edits accounted for
	}
	if ( $edits_accounted_for < $total_edits ) {
		print "<tr><td colspan=2><i tt='other_editors'></i></td><td nowrap class='right_num'>" . number_format($total_edits-$edits_accounted_for) . "</td></tr>" ;
	}
	print "</tbody></table>" ;
}


// Hash summary ~= tool used
$total_edits = 0 ;
$tag2cnt = [] ;
foreach ( $wits->stats AS $k => $v ) {
	$j = $v->json ;
	foreach ( $j->edits AS $k => $cnt ) {
		$k = json_decode ( $k ) ;
		if ( !isset($k->hash) or $k->hash=='' ) continue ;
		if ( preg_match ( '/^\d+;/' , $k->hash ) ) continue ;
		if ( !isset($tag2cnt[$k->hash]) ) $tag2cnt[$k->hash] = 0 ;
		$tag2cnt[$k->hash] += $cnt ;
		$total_edits += $cnt ;
	}
}
if ( count($tag2cnt) > 0 ) {
	print "<h3 tt='top_hashtags'></h3>" ;
	asort ( $tag2cnt ) ;
	$tag2cnt = array_reverse ( $tag2cnt , true ) ;
	$cnt = 0 ;
	$edits_accounted_for = 0 ;
	print "<table class='table table-striped table-sm'><thead><tr><th tt='hashtag'></th><th tt='edits'></th></tr></thead><tbody>" ;
	foreach ( $tag2cnt AS $name => $u ) {
		$edits_accounted_for += $u ;
		$cnt++ ;
		print "<tr><td>{$name}</td><td nowrap class='right_num'>" . number_format($u) . "</td></tr>" ;
		if ( $cnt >= 5 and $edits_accounted_for >= $total_edits*0.9 ) break ; // Top 5 hashtags, >=90% edits accounted for
	}
	if ( $edits_accounted_for < $total_edits ) {
//		print "<tr><td colspan=2><i tt='other_editors'></i></td><td nowrap class='right_num'>" . number_format($total_edits-$edits_accounted_for) . "</td></tr>" ;
	}
	print "</tbody></table>" ;
}
//print "<pre>" ; print_r ( $tag2cnt ) ; print "</pre>" ;

function getItemUsageOnWiki ( $wiki , &$item_usage ) {
	global $wits ;
	$dbw = openDBwiki ( $wiki ) ;
	$sql = "SELECT page_title,eu_page_id,group_concat(DISTINCT eu_entity_id) AS items,count(*) AS cnt from page,wbc_entity_usage WHERE page_id=eu_page_id AND page_namespace=0 AND eu_entity_id IN ('" . implode("','",$wits->all_items) . "') GROUP BY eu_page_id ORDER BY cnt DESC" ;
	if(!$result = $dbw->query($sql)) die('There was an error running the query [' . $dbw->error . ']');
	while($o = $result->fetch_object()) $item_usage[$wiki][$o->page_title] = $o ;
}

$wikipedias = $wits->catalog->wikis ;
if ( count($wits->all_items) > 0 and $wikipedias != '' ) {
	print "<h3>Usage on $wikipedia Wikipedia</h3>" ;
	print "<p><i>THis might be wrong</i></p>" ;
	$wikipedias = explode ( ',' , $wikipedias ) ;
	$item_usage = [] ;
	foreach ( $wikipedias AS $wiki ) {
		$wiki = trim(strtolower($wiki)) ;
		if ( !preg_match ( '/wiki/' , $wiki ) ) $wiki .= 'wiki' ;
		getItemUsageOnWiki ( $wiki , $item_usage ) ;
	}
	foreach ( $item_usage AS $wiki => $pages ) {
		$server = getWebserverForWiki ( $wiki ) ;
		list ( $wl , $wp ) = explode ( '.' , $server ) ;
		print "<h4>$wl $wp</h4>" ;
		print "<table class='table table-condensed table-striped table-sm'>" ;
		print "<thead><tr><th tt='page'></th><th tt='items_used'></th><th tt='num_usage'></th></tr></thead>" ;
		print "<tbody>" ;
		foreach ( $pages AS $p ) {
			print "<tr>" ;
			print "<td nowrap><a href='https://{$server}/wiki/" . myurlencode($p->page_title) . "' target='_blank' class='external'>" . str_replace('_',' ',$p->page_title) . "</a></td>" ;
			print "<td style='font-size:9pt;width:100%'>" ;
			$pi = explode ( ',' , $p->items ) ;
			foreach ( $pi AS $k => $i ) {
				if ( $k > 0 ) print ", " ;
				print "<a href='https://www.wikidata.org/wiki/$i' class='wikidata' target='_blank'>$i</a>" ;
			}
			print "</td>" ;
			print "<td nowrap class='right_num'>" . number_format($p->cnt) . "</td>" ;
			print "</tr>" ;
		}
		print "</tbody></table>" ;
	}
//	print "<pre>" ; print_r ( $item_usage ) ; print "</pre>" ;
}

?>
<h2 tt='details'></h2>
<table class='table'>
<thead>
<tr>
<th tt='month'></th>
<th tt='during_month'></th>
<th tt='diffs'></th>
</tr>
</thead>
<tbody>
<?PHP
	
	// Gather properties
	$props = [] ;
	foreach ( $wits->stats AS $k => $v ) {
		$j = $v->json ;
		if ( count($j->edits) > 0 ) {
			foreach ( $j->edits AS $k => $dummy ) {
				$k = json_decode ( $k ) ;
				foreach ( $k AS $part => $val ) {
					if ( $part == 'property' and preg_match('/^P\d+$/',$val) ) $props[$val] = $val ;
				}
			}
		}
	}
	$wil = new WikidataItemList() ;
	$wil->loadItems ( $props ) ;
	
	foreach ( $wits->stats AS $k => $v ) {
		$j = $v->json ;
		$ym = substr ( $j->start_date , 0 , 6 ) ;
		print "<tr>" ;
		print "<td nowrap><a name='month_{$v->year}_{$v->month}'></a>{$v->year}-" . ($v->month>9?'':'0') . "{$v->month}</td>" ;

		print "<td style='width:100%'>" ;
		
		print "<table class='table-condensed table-sm'><tbody>" ;
		
		// General stats
		$h = '<div class="list-group">' ;
		$cnt = 0 ;
		foreach ( $j->items->created AS $q ) {
			$h .= getItemBox ( $q ) ;
			$cnt++ ;
		}
		$h .= '</div>' ;
		if ( $cnt == 0 ) $h = '' ;
		print expandBox ( $cnt , "<span tt='items_were_created'></span>" , $h ) ;

		print expandBox ( $j->items->do_not_exist_before , "<span tt='items_did_not_exist_before_this_period' tt1='".number_format($j->items->sparql)."'></span>" , '' ) ;
		
		// Items changed
		$h = '' ;
		$changes = 0 ;
		$items_changed = 0 ;
		arsort ( $j->items->changed , SORT_NUMERIC ) ;
		$h2 = '' ;
		foreach ( $j->items->changed AS $q => $cnt ) {
			$h2 = getItemBox ( $q , "<span tt='edited_times' tt1='$cnt'></span>" ) . $h2 ;
			$changes += $cnt ;
			$items_changed++ ;
		}
		$h .= '<div class="list-group">' . $h2 . '</div>' ;
		
		print expandBox ( $items_changed , "<span tt='items_changed_total_edits' tt1='".number_format($changes)."'></span>" , $h ) ;
		
		// Edits
		if ( count($j->edits) > 0 ) {
			$edits = (array) $j->edits ;
			uasort ( $edits , 'compare_edits' ) ;
			$parts = [] ;
			foreach ( $edits AS $k => $dummy ) {
				$k = json_decode ( $k ) ;
				foreach ( $k AS $part => $dummy2 ) $parts[] = $part ;
				break ;
			}
			$h = '' ;
			$h .= "<table class='table table-condensed table-striped table-sm'>" ;
			$h .= "<thead><tr>" ;
			foreach ( $parts AS $part ) $h .= "<th>" . ucfirst(str_replace('_',' ',$part)) . "</th>" ;
			$h .= "<th tt='edits'></th>" ;
			$h .= "</tr></thead><tbody>" ;
			$edit_count = 0 ;
			foreach ( $edits AS $k => $cnt ) {
				$edit_count += $cnt ;
				$k = json_decode ( $k ) ;
				$h .= "<tr>" ;
				foreach ( $parts AS $part ) {
					$val = trim ( preg_replace ( '/^\d\|/' , '' , $k->$part ) ) ;
					$val = trim ( preg_replace ( '/^\|\d/' , '' , $val ) ) ;
					$h .= "<td>" ;
					if ( $part == 'property' and $val != '' ) {
						$i = $wil->getItem ( $val ) ;
						$label = $val ;
						if ( isset($i) ) $label = $i->getLabel($tt->getLanguage()) ;
						$h .= "<a class='wikidata' href='https://www.wikidata.org/wiki/Property:$val' target='_blank'>$label</a> <small>[$val]</small>" ;
					} else $h .= escape_attribute($val) ;
					$h .= "</td>" ;
				}
				$h .= "<td class='right_num'>" . number_format($cnt) . "</td>" ;
				$h .= "</tr>" ;
			}
			$h .= "</tbody></table>" ;
			print expandBox ( $edit_count , "<span tt='edits_during_this_month'></span>" , $h ) ;
		}
		
		// Users
		if ( count($j->users) > 0 ) {
			usort ( $j->users , 'compare_users' ) ;
			$h = '' ;
			$h .= "<table class='table table-condensed table-striped table-sm'>" ;
			$h .= "<thead><tr><th tt='user'></th><th tt='type'></th><th style='text-align:right' tt='edits_this_month'></th><th style='text-align:right' tt='total_edits_wd'></th></tr></thead><tbody>" ;
			foreach ( $j->users AS $u ) {
				$h .= "<tr>" ;
				$h .= "<td>" ;
				$h .= getLinkedUserName ( $u->name , $u->type ) ;
				$h .= "</td>" ;
				$h .= "<td>{$u->type}</td>" ;
				$h .= "<td class='right_num'>" . number_format($u->edits) . "</td>" ;
				$h .= "<td class='right_num'>" . number_format($u->edit_count) . "</td>" ;
				$h .= "</tr>" ;
			}
			$h .= "</tbody></table>" ;
			print expandBox ( count($j->users) , "<span tt='users_edited_total'></span>" , $h ) ;
		}
		
		print expandBox ( '' , "<span tt='data_generated_on' tt1='" . $wits->prettyTS($v->timestamp) . "'></span>" , '' ) ;
		
		print "</tbody></table>" ;
		
		print "</td>" ;
		
		print "<td nowrap><a target='_blank' href='https://tools.wmflabs.org/wikidata-todo/sparql_rc.php?start={$ym}01&end={$ym}31&sparql=" . urlencode($wits->catalog->sparql) . "&skip_unchanged=1' tt='diffs'></a></td>" ;
		print "</tr>" ;
	}
	
	print "</tbody></table>" ;

?>

<script>
$(document).ready ( function () {
	$('a.mybox_show').click ( function () {
		var a = $(this) ;
		$(a.parents('div.mybox')[0]).find('div.mybox_contents').show() ;
		$(a.parents('div.mybox')[0]).find('a.mybox_hide').show() ;
		a.hide() ;
		return false ;
	} ) ;
	$('a.mybox_hide').click ( function () {
		var a = $(this) ;
		$(a.parents('div.mybox')[0]).find('div.mybox_contents').hide() ;
		$(a.parents('div.mybox')[0]).find('a.mybox_show').show() ;
		a.hide() ;
		return false ;
	} ) ;
} ) ;
</script>

<?PHP
	print get_common_footer() ;
} else {
	print get_common_header ( '' , 'Wikidata Item Tracking System' ) ;
	print "<div style='color:red;font-size:32pt'>THIS TOOL IS CURRENTLY UNDERGOING REWRITE</div>" ;
}


?>
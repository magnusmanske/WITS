<?PHP

require_once ( '/data/project/wits/public_html/php/common.php' ) ;

class WITS {

	public $data = [
		'items' => [ 'created' => [] , 'changed' => [] , 'sparql'=>0 , 'do_not_exist_before'=>0 ] ,
		'edits' => [] ,
		'users' => [] ,
		'timestamp' => ''
	] ;
	public $catalog_id ;
	public $year , $month ;
	public $catalog ;
	public $stats = [] ;
	
	protected $items = [] ;
	protected $bots = [] ;

	function __construct ( $_catalog_id = 0 , $_year = 0 , $_month = 0 ) {
		$this->catalog_id = $_catalog_id ;
		$this->year = $_year ;
		$this->month = $_month ;
	}
	
	public function generateStats ( $force = false ) {
		// Init
		$this->data['timestamp'] = date ( "YmdHis" ) ;
		$this->loadCatalog() ;
		$this->data['start_date'] = $this->year . ($this->month>9?'':'0') . $this->month . "00000000" ;
		$this->data['end_date'] = $this->year . ($this->month>9?'':'0') . $this->month . "32000000" ;
		
		// Check/prevent re-creation
		if ( !$force and $this->doStatsExist() ) $this->logErrorAndExit ( "Catalog {$this->catalog_id} already has data for {$this->year}-{$this->month}" ) ;
		
		// Generate stats
		$this->getItemsFromSPARQL() ;
		$this->getBotUsers() ;
		$this->checkItemsExist() ;
		$this->checkRevisions() ;
		$this->expandUserInfo () ;
		$this->writeStatsToDatabase () ;
	}
	
	public function doStatsExist () {
		$db = $this->getDBtool() ;
		$sql = "SELECT * FROM stats WHERE year={$this->year} AND month={$this->month} AND catalog_id={$this->catalog_id}" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:1');
		if($o = $result->fetch_object()) return true ;
		return false ;
	}
	
	public function getCatalogs () {
		$ret = [] ;
		$db = $this->getDBtool() ;
		$sql = "SELECT * FROM catalog" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:9a'."\n$sql\n");
		while($o = $result->fetch_object()) {
			$o->stats_count = 0 ;
			$ret[''.$o->id] = $o ;
		}
		$sql = "SELECT catalog_id,count(*) AS cnt FROM stats GROUP BY catalog_id" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:9b'."\n$sql\n");
		while($o = $result->fetch_object()) {
			if ( isset($ret[''.$o->catalog_id]) ) $ret[''.$o->catalog_id]->stats_count = $o->cnt ;
		}
		return $ret ;
	}

	public function fillStats () {
		// Get current year/month
		$year =  date ( "Y" ) * 1 ;
		$month =  date ( "m" ) * 1 ;
	
		// Create possible year/month combinations
		$ym = [] ;
		$y = 2014 ;
		$m = 1 ;
		while ( $y*100+$m < $year*100+$month ) {
			if ( $m > 12 ) { $m = 1 ; $y++ ; }
			$ym["$y|$m"] = [ $y , $m ] ;
			$m++ ;
		}
	
		// Remove year/month that already exist
		$db = $this->getDBtool() ;
		$sql = "SELECT * FROM stats WHERE catalog_id=" . $this->catalog_id ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:8'."\n$sql\n");
		while($o = $result->fetch_object()) {
			$k = $o->year . '|' . $o->month ;
			if ( isset($ym[$k]) ) unset ( $ym[$k] ) ;
		}
	
		// Generate missing stats
		foreach ( $ym AS $d ) {
//print $d[0] . ':' . $d[1] . "\n" ;
			$wits = new WITS ( $this->catalog_id , $d[0] , $d[1] ) ;
			$wits->generateStats ( false ) ;
		}
	}

	public function loadCatalog () {
		if ( isset($this->catalog) ) return ;
		if ( $this->catalog_id == 0 ) $this->logErrorAndExit('No catalog set');
		$db = $this->getDBtool() ;
		$sql = "SELECT * FROM catalog WHERE id={$this->catalog_id}" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:4');
		while($o = $result->fetch_object()) $this->catalog = $o ;
		if ( !isset($this->catalog) ) $this->logErrorAndExit ( "Catalog {$this->catalog_id} does not exist" ) ;
	}

	public function loadCatalogStats () {
		$this->loadCatalog() ;
		$db = $this->getDBtool() ;
		$sql = "SELECT * FROM stats WHERE catalog_id={$this->catalog_id} ORDER BY year,month" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:10');
		while($o = $result->fetch_object()) {
			$o->json = json_decode ( $o->json ) ;
//			$o->items = json_decode ( $o->items ) ;
			$this->stats[] = $o ;
		}
		$this->stats = array_reverse ( $this->stats ) ;
	}

	public function prettyTS ( $ts ) {
		return substr($ts,0,4).'-'.substr($ts,4,2).'-'.substr($ts,6,2).'&nbsp;'.substr($ts,8,2).':'.substr($ts,10,2).':'.substr($ts,12,2) ;
	}
	
	public function getLinkedName ( $catalog = '' ) {
		if ( $catalog == '' ) $catalog = $this->catalog ;
		if ( $catalog->subject_item == '' ) return $catalog->name ;
		return "<a href='https://tools.wmflabs.org/reasonator/?q={$catalog->subject_item}' target='_blank' class='external'>{$catalog->name}</a>" ;
	}

	
	// ________________________________________________________________________________________________________________________________________________________________

	protected function getDBtool () {
		return openToolDB ( 'wits_p' ) ;
	}
	
	protected function getDBwikidata () {
		return openDB ( 'wikidata' , 'wikidata' ) ;
	}
	
	protected function checkItemsExist () {
		if ( count($this->items) == 0 ) return ;
		$db = $this->getDBwikidata() ;
		$sql = "SELECT DISTINCT page_title FROM page,revision WHERE rev_page=page_id" ;
		$sql .= " AND rev_timestamp>='{$this->data['start_date']}' AND rev_parent_id=0" ;
		$sql .= " AND page_namespace=0 AND page_title IN ('" . implode ( "','" , $this->items ) . "')" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:2');
		$this->data['items']['do_not_exist_before'] = 0 ;
		while($o = $result->fetch_object()) $this->data['items']['do_not_exist_before']++ ;
	}
	
	protected function checkRevisions () {
		if ( count($this->items) == 0 ) return ;
		$db = $this->getDBwikidata() ;
		$sql = "SELECT page.page_title,revision.* FROM page,revision WHERE rev_page=page_id" ;
		$sql .= " AND rev_timestamp BETWEEN '{$this->data['start_date']}' AND '{$this->data['end_date']}'" ;
		$sql .= " AND page_namespace=0 AND page_title IN ('" . implode ( "','" , $this->items ) . "')" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:2');
		while($o = $result->fetch_object()){
			if ( $o->rev_parent_id == 0 ) $this->data['items']['created'][] = $o->page_title ;
			if ( !isset($this->data['items']['changed'][$o->page_title]) ) $this->data['items']['changed'][$o->page_title] = 1 ;
			else $this->data['items']['changed'][$o->page_title]++ ;

			$key = [] ;

			if ( isset($this->bots[$o->rev_user]) ) $key['user'] = 'bot' ;
			else if ( $o->rev_user == 0 ) $key['user'] = 'anon' ;
			else $key['user'] = 'user' ;
	
			if ( preg_match ( '/\#(\S+)/' , $o->rev_comment , $m ) ) $key['hash'] = $m[1] ;
			else $key['hash'] = '' ;
	
			if ( preg_match ( '/^\/\*\s*(\S+?):(.*?) \*\//' , $o->rev_comment , $m ) ) {
				$key['action'] = $m[1] ;
				$key['action_param'] = $m[2] ;
			} else {
				$key['action'] = 'unknown' ;
				$key['action_param'] = '' ;
			}
	
			if ( preg_match ( '/\*\/\s+\[\[Property:(P\d+)\]\]:/' , $o->rev_comment , $m ) ) $key['property'] = $m[1] ;
			else $key['property'] = '' ;
	
			ksort ( $key ) ;
			$key = json_encode ( $key ) ;
			if ( !isset($this->data['edits'][$key]) ) $this->data['edits'][$key] = 1 ;
			else $this->data['edits'][$key]++ ;
	
			if ( !isset($this->data['users'][$o->rev_user]) ) $this->data['users'][''.$o->rev_user] = [ 'edits' => 1 ] ;
			else $this->data['users'][''.$o->rev_user]['edits']++ ;
		}
	}
	
	protected function getBotUsers () {
		$db = $this->getDBwikidata() ;
		$sql = "SELECT ug_user FROM user_groups WHERE ug_group='bot'" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:3');
		while($o = $result->fetch_object()) $this->bots[$o->ug_user] = $o->ug_user ;
	}
	
	protected function getItemsFromSPARQL () {
		if ( !preg_match ( '/^\s*select\s+\?(\S+)/i' , $this->catalog->sparql , $m ) ) $this->logErrorAndExit ( "Bad SPARQL: {$this->catalog->sparql}" ) ;
		$varname = $m[1] ;
		$this->items = getSPARQLitems ( $this->catalog->sparql , $varname ) ;
		foreach ( $this->items AS $k => $v ) $this->items[$k] = "Q$v" ; // Operate only with full IDs
		$this->data['items']['sparql'] = count($this->items) ;
	}
	
	protected function logErrorAndExit ( $msg ) {
		debug_print_backtrace() ;
		die ( "$msg\n" ) ; // For now, should go into a logfile
	}

	protected function expandUserInfo () {
		if ( count($this->data['users']) == 0 ) return ;
		$db = $this->getDBwikidata() ;
		$sql = "SELECT * FROM user WHERE user_id IN (" . implode(',',array_keys($this->data['users'])) . ")" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:5'."\n$sql\n");
		while($o = $result->fetch_object()) {
			$this->data['users'][''.$o->user_id]['name'] = $o->user_name ;
			$this->data['users'][''.$o->user_id]['edit_count'] = $o->user_editcount ;
			$this->data['users'][''.$o->user_id]['name'] = $o->user_name ;
			$this->data['users'][''.$o->user_id]['type'] = 'user' ;
		}
		if ( isset($this->data['users']['0']) ) {
			$this->data['users']['0']['name'] = 'anonymous editor' ;
			$this->data['users']['0']['edit_count'] = 0 ;
			$this->data['users']['0']['type'] = 'IP' ;
		}
		foreach ( $this->bots AS $b ) {
			if ( isset($this->data['users']["$b"]) ) $this->data['users']["$b"]['type'] = 'bot' ;
		}
		$this->data['users'] = array_values ( $this->data['users'] ) ; // Drop user IDs from public data
	}

	protected function writeStatsToDatabase () {
		$db = $this->getDBtool() ;
		$sql = "DELETE FROM stats WHERE year={$this->year} AND month={$this->month} AND catalog_id={$this->catalog_id}" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:6');

		$sql = "INSERT INTO stats (catalog_id,year,month,`timestamp`,json,items) VALUES ({$this->catalog_id},{$this->year},{$this->month},'{$this->data['timestamp']}','" ;
		$sql .= $db->real_escape_string ( json_encode($this->data) ) ;
		$sql .= "','" ;
		$sql .= $db->real_escape_string ( json_encode($this->items) ) ;
		$sql .= "')" ;
		if(!$result = $db->query($sql)) $this->logErrorAndExit('There was an error running the query [' . $db->error . ']:7');
//print_r ( $this->data ) ;
	}
	
}


?>
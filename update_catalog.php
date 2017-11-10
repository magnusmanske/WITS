#!/usr/bin/php
<?PHP

require_once ( '/data/project/wits/wits.php' ) ;

if ( isset($argv[2]) and $argv[1] == 'fill' ) {
	$catalog_id = $argv[2]*1 ; // TODO check
	$wits = new WITS ( $catalog_id ) ;
	$wits->fillStats() ;
	exit ( 0 ) ;
}


if ( !isset($argv[3]) ) $this->logErrorAndExit ( "Usage: update_catalog_month.php YEAR MONTH CATALOG_ID\n" ) ;
$year = $argv[1] * 1 ;
$month = $argv[2] * 1 ;
$catalog_id = $argv[3] * 1 ;
if ( $year*$month*$catalog_id == 0 ) $this->logErrorAndExit ( "Usage: update_catalog_month.php YEAR MONTH CATALOG_ID\n" ) ; // Make sure none of them are 0...
$force = (isset($argv[4]) and $argv[4]=='force') ;

$wits = new WITS ( $catalog_id , $year , $month ) ;
$wits->generateStats ( $force ) ;

?>
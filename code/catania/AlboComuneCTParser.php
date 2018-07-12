<?php
/**
 * This class allows one to get and parse the entries of a specified year in the Albo 
 * Pretorio of the municipality of Catania.
 *  
 * Copyright 2016 Cristiano Longo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Cristiano Longo
 */

//the following url is url-encoded
define('ALBO_CT_URL','http://www.comune.catania.gov.it/EtnaInWeb/AlboPretorio.nsf/Web%20Ricerca?OpenForm&AutoFramed');
//define('ALBO_CT_URL','http://www.comune.catania.gov.it/EtnaInWeb/AlboPretorio.nsf/8f8792f746272ce9c1257474003665cd?OpenForm&AutoFramed');

//number of months before today from which retrieve the notices
define("NMONTHS","1");

/**
 *  Here we provide some utilitymethods t download the notices page from the web site of the municipality of catania
 */
class AlboComuneCTDonwloader{
	private $url;

	/**
	 *
	 * @param $url the URL of the notice board page
	 */
	public function __construct($url) {		
		$this->url=$url;
	}

	/**
	 * Factory method to construct an instance which retrieves entries from
	 * a default period of time ago.
	 */
	public  function createByDate(){
		$date=new DateTimeImmutable();
		$from_date=$date->sub(new DateInterval('P'.NMONTHS.'M'));
		
		$h=curl_init($this->url);
		if (!$h) throw new Exception("Unable to initialize cURL session");
		
		curl_setopt($h, CURLOPT_POST, TRUE);
		curl_setopt($h, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($h, CURLOPT_POSTFIELDS,
				array("__Click" => 0,
						"%%Surrogate_Attivi"=>"1",
						"%%Surrogate_gg1"=>1, "gg1"=>$from_date->format('d'),
						"%%Surrogate_mm1"=>1, "mm1"=>$from_date->format('m'),
						"%%Surrogate_aa1"=>1, "aa1"=>$from_date->format('Y')
				));
		//curl_setopt($h, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
		$page=curl_exec($h);
		if( $page==FALSE)
			throw new Exception("Unable to execute POST request: ".curl_error($h));
		curl_close($h);
		return $page;
	}

}

/**
 * Convenience class to represent single entry of the municipality of Catania Albo.
 *
 * @author Cristiano Longo
 *
 */
class AlboComuneCTEntry{
	public $anno;
	public $numero;
	public $repertorio;
	public $link;
	public $tipo;
	public $mittente_descrizione;

	/**
	 * Create an entry by parsing a table row.
	 */
	public function __construct($row) {
		$cells=$row->getElementsByTagName("td");
		$repertorioAnchorNodes=$cells->item(1)->getElementsByTagName("a");
		if ($repertorioAnchorNodes->length==0)
			throw new Exception("No anchor found in repertorio");
		if ($repertorioAnchorNodes->length>1)
			throw new Exception("Multiple anchor nodes found in repertorio");
		$repertorioAnchorNode=$repertorioAnchorNodes->item(0);
		$this->repertorio=$repertorioAnchorNode->textContent;
		$repertorioPieces=explode('/',$this->repertorio);
		$this->anno=trim($repertorioPieces[0]);
		$this->numero=trim($repertorioPieces[1]);		
		
		$this->link="http://www.comune.catania.gov.it".$repertorioAnchorNode->getAttribute("href");
		$this->tipo=html_entity_decode(utf8_decode($cells->item(3)->textContent));
		$this->mittente_descrizione=html_entity_decode(utf8_decode($cells->item(4)->textContent));
	}

	/**
	 * Retrieve the albo pages with all the notices of the current year
	 */
	public static function createByYear(){
		date_default_timezone_set("Europe/Rome");
		$t=new DateTime();
		$currentYear=$t->format('Y');
		
		$h=curl_init($this->url);
		if (!$h) throw new Exception("Unable to initialize cURL session");
		curl_setopt($h, CURLOPT_POST, TRUE);
		curl_setopt($h, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($h, CURLOPT_POSTFIELDS, array("__Click" => 0, "Anno"=>$currentYear, "Numero" => ""));
	
		//curl_setopt($h, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
		$page=curl_exec($h);
		if( $page==FALSE)
			throw new Exception("Unable to execute POST request: "+curl_error());
		curl_close($h);
		return $page;
	}

}
/**
 * Get and parse the entries of a single year of the Albo Pretorio of the municipality
 * of Catania.
 *
 * @author Cristiano Longo
 *
 */
class AlboComuneCTParser implements Iterator{

	private $rows;
	private $items;
	private $i=1;
	
	/**
	 *  Retrieve the entries in the notice board results page.
	 *  
	 *  @param $from_date a DateTime object
	 *  @param $to_date a DataTime object
	 */
	public function __construct($page) {		
		$this->rows=$this->getRows($page);
	}

	/**
	 * Factory method to construct an instance which retrieves entries from
	 * a default period of time ago.
	 */
	public static function createByDate(){
		$d = new AlboComuneCTDonwloader(ALBO_CT_URL);
		return new AlboComuneCTParser($d->createByDate());
	}
	
	/**
	 * Retrieve the single notice with the specified number and year.
	 * @param $year
	 * @param $number
	 */
	public static function createByRepertorio($year, $number){
		$h=curl_init(ALBO_CT_URL);
		if (!$h) throw new Exception("Unable to initialize cURL session");
		curl_setopt($h, CURLOPT_POST, TRUE);
		curl_setopt($h, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($h, CURLOPT_POSTFIELDS, array("__Click" => 0, "Anno"=>$year, "Numero"=>$number));
				
		//curl_setopt($h, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
		$page=curl_exec($h);
		if( $page==FALSE)
			throw new Exception("Unable to execute POST request: "+curl_error());
		curl_close($h);
		return new AlboComuneCTParser($page);
	}
	
	/**
	 * Retrieve the albo pages with all the notices of the current year
	 */
	public static function createByYear(){
		date_default_timezone_set("Europe/Rome");
		$t=new DateTime();
		$currentYear=$t->format('Y');
		
		$h=curl_init(ALBO_CT_URL);
		if (!$h) throw new Exception("Unable to initialize cURL session");
		curl_setopt($h, CURLOPT_POST, TRUE);
		curl_setopt($h, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($h, CURLOPT_POSTFIELDS, array("__Click" => 0, "Anno"=>$currentYear, "Numero" => ""));
	
		//curl_setopt($h, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
		$page=curl_exec($h);
		if( $page==FALSE)
			throw new Exception("Unable to execute POST request: "+curl_error());
		curl_close($h);
		return new AlboComuneCTParser($page);
	}
	
	/**
	 * Extract the rows of the table containing the Albo Entries from a result page.
	 *
	 * @param string $page
	 */
	private function getRows($page){
		$d=new DOMDocument();
 		$d->loadHTML($page);
 		$tables=$d->getElementsByTagName("table");
 		if ($tables->length==0)
 			throw new Exception("No table element found");
 		if ($tables->length>1)
 			throw new Exception("Multiple table elements found");
		$rows=$tables->item(0)->getElementsByTagName("tr");
		return $rows; 			
	}
	
	//helper function
	/**
	 * Get the (first) item if any, null otherwise.
	 */
	public function getFirst($repertorio){
		if ($this->rows->length<2) 
			return null;
		return new AlboComuneCTEntry($this->rows->item(0));
		for($j=1; $j<$this->rows->length; $j++){
			$entry=new AlboComuneCTEntry($this->rows->item($j));
			if (!strcmp($repertorio, $entry->repertorio))
				return $entry;
		}
		return null;
	}
	
	//Iterator functions,  see http://php.net/manual/en/class.iterator.php
	
	public function current(){
		if ($this->rows->length<2)
			return null;
		return new AlboComuneCTEntry($this->rows->item($this->i));
	}
	
	
	public function key (){
		return $this->i;
	}
	
	public function next(){
		if ($this->i<$this->rows->length)
			++$this->i;
	}
	
	public function rewind(){
		if ($this->rows->length>1)
			$this->i=1;
	}
	
	public function valid(){
		return $this->rows->length>1 && $this->i<$this->rows->length;
	}
}
?>
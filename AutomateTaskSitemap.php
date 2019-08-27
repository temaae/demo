<?php
defined('_INITIATED') or die;

/** 
* AutomateTaskSitemap для генерации карты сайта по расписанию из CLI
*/
class AutomateTaskSitemap extends AstractSitemap
{

	static private $mapdir = PATHBASE.'/sitemap/';

	// Путь к контроллерам (в понимании MVC) сайта 
	static private $sections_path = PATHBASE.'/sections/';

	private $files = array();
	private $filename = '';

	// Максимальное число страниц в одном файле 
	private $maxitemsinfile = 40000;


	function __construct() {
		$this->filename = self::$mapdir.'sitemap';
	}

	/**
	 * createFile() создает очередной файл в последовательности и инициализирует его
	 */
	private function createFile(){

		$i = count($this->files);
		$fname = $this->filename;
		
		$fname = $fname."_".($i+1);
	

		$this->file = fopen($fname.'.xml', 'w');

		$this->files[] = $this->file;

		fwrite($this->file, "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n");
		fwrite($this->file, "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n");

		$this->itemcounter = 0;
	}


	/** 
	 * Метод закрывает сгенерированные файлы и создает архивы
	 */
	private function closeFiles(){
		for ($i = 0; $i<count($this->files); $i++ ){
			$fname = $this->filename;

			$fname = $fname."_".($i+1);
			$this->file = $this->files[$i];
			fwrite($this->file, "</urlset>\n");
			fclose($this->file);

			copy($fname.'.xml', 'compress.zlib://' . $fname.'.xml.gz');

			
			unlink($fname.'.xml');


		}
	}

	/** 
	 * Записывает очередную ссылку-страницу в текущий файл карты
	 */
	public function writeUrl($url){
		if ($this->itemcounter >= $this->maxitemsinfile){
			$this->createFile();
		};

		$this->itemcounter++;
		fwrite($this->file, "<url>\n");	
		fwrite($this->file, "<loc>{$url}</loc>\n");	
		fwrite($this->file, "</url>\n");	

	}


	/**
	 * Входная точка алгоритма
	 */
	public function execute(){
		$router = ALDRouter::getInstance();

		// Создаем временный файл для лирнейной записи всех адресов 
		$tfname = tempnam(sys_get_temp_dir(), 'Sitemap');

		// Чистим старые файлы с картой сайта
		exec("rm -rf ".self::$mapdir.'sitemap*');

		// Если файл существует и в него можно писать
		if (is_writable($tfname)) {
			$tfile =  fopen($tfname, 'w');
			// Записываем корневой элемент
			$href = $router->rawUrlToSef("/index.php");
			fwrite($tfile, $href."\n");

			$sections = scandir(self::$sections_path);
			// Запрашиваем у каждого контроллера сайта список актывных адресов
			foreach ($sections as $section) {
				if (is_dir(self::$sections_path.$section) && ($section<>'.') && ($section<>'..') ){
					$incfile = self::$sections_path.$section."/sitemap.php";
					//Если контролер содержит механизм выгрузки адресов
					if (file_exists($incfile)){
						$className = ucfirst($section).'Sitemap';
						if (!class_exists( $className )){
							require_once $incfile;	
							$smgenerator = new $className;
							$smgenerator->generate($tfile);
						}
					}

				}
			}
			
			fclose($tfile);


			// Дробим файл на части
			$this->createFile();
			$tfile =  fopen($tfname, 'r');

			while (!feof($tfile)) {
			    $buffer = fgets($tfile);
			    

			    $this->writeUrl(trim($buffer) );

			}
			fclose($tfile);

			$this->closeFiles();

			//  Создаем корневой файл и прописываем в него ссылки на файлы-саттелиты 
			$gfname = $this->filename;
			$gfile = fopen($gfname.'.xml', 'w');

			fwrite($gfile, "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n");
			fwrite($gfile, "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n");

			for ($i = 0; $i<count($this->files); $i++ ){
				$fname = $this->filename;
				$fname = $fname."_".($i+1);
				$shortname = str_replace(self::$mapdir, '', $fname.'.xml');
				fwrite($gfile, "<sitemap><loc>https://azm24.ru/sitemap/{$shortname}.gz</loc></sitemap>\n");
			}


			fwrite($gfile, "</sitemapindex>\n");
			fclose($gfile);


		}
	}



}	

?>

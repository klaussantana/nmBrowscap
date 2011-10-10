<?php
/**
 * nmBrowscap (biblioteca)
 * 
 * Uma biblioteca responsável por detectar o navegador utilizado pelo usuário.
 * 
 * A biblioteca verifica a possibilidade de utilização da função nativa do
 * php `get_browser()` para recuperar as informações do navegador. Se não for
 * possível utilizar a função nativa do php então a biblioteca irá realizar o
 * download do arquivo `php_browscap.ini` e o processará manualmente, criando
 * um cache (`tmp/nmBrowscap.cache`) e retornando as informações deste cache.
 * 
 * NOTA: Esta biblioteca não pode ser instanciada e nem clonada.
 * 
 * Exemplo:
 * ========
 * <?php
 *    nanoMax::Assembly('nmBrowscap');
 *    
 *    echo 'Você está utilizando o navegador ' .nanoMax::GetBrowserName() .'.';
 * ?>
 * 
 * @package      nanoMax
 * @subpackage   nmBrowscap
 * @category     Library-Browscap
 * @author       Klauss Sant'Ana Guimarães
 * @copyright    Copyright (c) klaussantana.com
 * @license      http://www.gnu.org/licenses/lgpl.html LGPL - LESSER GENERAL PUBLIC LICENSE
 * @link         http://nanoMax.klaussantana.com
 * @version      0.1-dev
 * @filesource   
 **/
class nmBrowscap extends nmGear
{
	/**
	 * Contém as definições do navegador.
	 *
	 * @static
	 * @access   public
	 * @var      array     Definições do navegador.
	 **/
	static public $Browser =null;
	
	/**
	 * Construtor da classe
	 *
	 * Não é possível instanciar esta classe deliberadamente.
	 *
	 * @access   private
	 **/
	private
	function __construct()
	{}
	
	/**
	 * Clonador da classe
	 *
	 * Não é possível clonar esta classe.
	 *
	 * @access   private
	 **/
	private
	function __clone()
	{}
	
	/**
	 * Adquire o nome e versão do navegador
	 *
	 * @static
	 * @access   public
	 * @param    string   $UserAgent - (Opcional) String contendo o `User Agent` do navegador a pesquisar. Se o valor for `null`, `false` ou omitido, a biblioteca irá detectar o navegador automaticamente.
	 * @return   string   Nome e versão do navegador especificado em `$UserAgent`.
	 **/
	static
	public
	function GetBrowserName($UserAgent =null)
	{
		if ( empty(static::$Browser) || ($UserAgent && ($UserAgent !=static::$Browser['useragent'])) )
		{
			static::GetBrowser($UserAgent);
		}
		
		return static::$Browser['browser'] .' ' .static::$Browser['version'];
	}
	
	/**
	 * Adquire a versão do navegador
	 *
	 * @static
	 * @access   public
	 * @param    string   $UserAgent - (Opcional) String contendo o `User Agent` do navegador a pesquisar. Se o valor for `null`, `false` ou omitido, a biblioteca irá detectar o navegador automaticamente.
	 * @return   string   Versão do navegador especificado em `$UserAgent`.
	 **/
	static
	public
	function GetBrowserVersion($UserAgent =null)
	{
		if ( empty(static::$Browser) || ($UserAgent && ($UserAgent !=static::$Browser['useragent'])) )
		{
			static::GetBrowser($UserAgent);
		}
		
		return static::$Browser['version'];
	}
	
	/**
	 * Adquire as informações do navegador
	 * 
	 * NOTA: Se o parâmetro `$UserAgent` for omitido ou equivaler
	 * a false (`null`, `false`, etc.) a biblioteca irá pesquisar
	 * na superglobal `$_SERVER` pelo índice `HTTP_USER_AGENT`.
	 *
	 * @static
	 * @access   public
	 * @param    string   $UserAgent - (Opcional) String contendo o `User Agent` do navegador a pesquisar. Se o valor for `null`, `false` ou omitido, a biblioteca irá detectar o navegador automaticamente.
	 * @return   array    Informações do navegador especificado em `$UserAgent`.
	 **/
	static
	public
	function GetBrowser( $UserAgent =null )
	{
		global $NM_DIR;
		
		// Se omitido, adquire o agente do usuário
		if ( !$UserAgent )
		{
			$UserAgent = $_SERVER['HTTP_USER_AGENT'];
		}
		
		// Se já foi pesquisado uma vez, não procura novamente
		if ( !empty(static::$Browser) && $UserAgent && ($UserAgent ==static::$Browser['useragent']) )
		{
			return static::$Browser;
		}
		
		// Verifica se pode utilizar a função nativa do php `get_browser()`
		if ( (int)(static::Configuration()->UseCoreBrowscap) && ini_get('browscap') && function_exists('get_browser') && $Browser =get_browser($UserAgent, true) )
		{
			// Apenas para efeito de log
			trigger_error(static::Language()->get_browser);
			
			// Já associa e retorna os valores de get_browser()
			static::$Browser = $Browser;
			static::$Browser['useragent'] = $UserAgent;
			return static::$Browser;
		}
		
		// O arquivo já processado em cache de `php_browscap.ini` (DS = DIRECTORY_SEPARATOR)
		$Browscap = $NM_DIR->tmp .DS .'nmBrowscap.cache';
		
		// Verifica se o cache existe e está atualizado
		if ( !is_file($Browscap) || !is_readable($Browscap) || ( (time() -filemtime($Browscap)) > (int)(static::Configuration()->UpdateInterval) ) )
		{
			// Procura por uma nova atualização de `php_browscap.ini`
			static::CheckForUpdates();
		}
		
		// Recupera o cache do `php_browscap.ini`
		$Browscap = unserialize(file_get_contents($Browscap));
		
		// Faz uma busca para encontrar o navegador
		foreach ( $Browscap['Browsers'] as $Key =>$Value )
		{
			if ( static::$Browser ==null )
			{
				if ( preg_match("@^{$Key}\$@i", $UserAgent) )
				{
					static::$Browser = $Value;
					break;
				}
			}
		}
		
		// Percorre os navegadores pais para herdar as propriedades
		while ( isset(static::$Browser['parent']) )
		{
			$Parent = preg_quote(static::$Browser['parent']);
			
			foreach ( (($Parent =='DefaultProperties') ? $Browscap['Defaults'] : $Browscap['Browsers'][$Parent]) as $Key =>$Value )
			{
				if ( !isset(static::$Browser[$Key]) || ($Key =='parent') )
				{
					static::$Browser[$Key] = $Value;
				}
			}
			
			if ($Parent =='DefaultProperties')
			{
				unset(static::$Browser['parent']);
			}
		}
		
		// Variáveis não devem ser logadas. Limpeza de memória.
		unset($Browscap, $Key, $Value, $Parent);
		
		static::$Browser['useragent'] = $UserAgent;
		
		// Apenas para efeito de log
		trigger_error(static::Language()->nmBrowscap);
		
		// Retorna as propriedades do navegador encontrado
		return static::$Browser;
	}
	
	/**
	 * Realiza verificação de atualizações no `php_browscap.ini`
	 * 
	 * Este método é responsável por verificar se o cache está atualizado de
	 * acordo com a definição de `nmBrowscap::Configuration()->UpdateInterval`.
	 *
	 * @static
	 * @access   public
	 * @param    void     Este método não recebe parâmetros.
	 * @return   bool     Se foi realizada atualização ou não.
	 **/
	static
	public
	function CheckForUpdates()
	{
		global $NM_DIR;
		
		// O arquivo já processado em cache de `php_browscap.ini` (DS = DIRECTORY_SEPARATOR)
		$Browscap = $NM_DIR->tmp .DS .'nmBrowscap.cache';
		
		// Verifica existência, acessibilidade e tempo de existência
		if ( !is_file($Browscap) || !is_readable($Browscap) || ((time() -filemtime($Browscap)) >intval(static::Configuration()->UpdateInterval)) )
		{
			// Se não passou, faz o download
			$File = static::Download();
			
			if ( $File )
			{
				// Processa o arquivo, armazena em cache e apaga o temporário
				$Sucess = static::CacheBrowscap($File);
				
				// Se houve sucesso no processamento retorna `true`
				return $Sucess;
			}
			
			else
			{
				// Não foi possível efetuar o download. Não atualizou.
				return false;
			}
		}
		
		else
		{
			// O cache já está atualizado
			return false;
		}
	}
	
	/**
	 * Realiza o download do arquivo `php_browscap.ini`
	 * 
	 * NOTA: O caminho para o arquivo remoto está indicado em
	 * `nmBrowscap::Configuration()->RemoteFile`.
	 *
	 * @static
	 * @access   private
	 * @param    void     Este método não recebe parâmetros.
	 * @return   mixed    Se foi realizado o download, retorna o caminho e nome do arquivo. Caso contrário retorna `false`.
	 **/
	static
	private
	function Download()
	{
		global $NM_DIR;
		
		// As configurações do PHP não permitem o download
		if ( !ini_get('allow_url_fopen') )
		{
			trigger_error(static::Language()->CantDownload);
			return false;
		}
		
		// Cria o contexto em que será realizado o download do arquivo remoto
		$Context = stream_context_create( array('http' => array('method' =>'GET', 'header' =>'User-agent: nanoMax - nmBrowscap crawler')) );
		
		// Carrega o arquivo remoto em uma variável para tratamento posterior
		$RemoteFile = file_get_contents(static::Configuration()->RemoteFile, 0, $Context);
		
		// Ocorreu um erro no download.
		if ( !strlen($RemoteFile) )
		{
			trigger_error(static::Language()->CantDownload);
			return false;
		}
		
		$LocalFile = $NM_DIR->tmp .DS .'nmBrowscap.' .time() .'.tmp';
		$FilePuts  = file_put_contents($LocalFile, $RemoteFile);
		
		// Retorna
		return $FilePuts ? $LocalFile : false;
	}
	
	/**
	 * Processa o arquivo temporário e armazena em cache.
	 * 
	 * NOTA: Este método é executado internamente.
	 * 
	 * @static
	 * @access   private
	 * @param    string   $Localfile - O caminho e nome do arquivo temporário a processar.
	 * @return   bool     Se foi armazenado ou não.
	 **/
	static
	private
	function CacheBrowscap($LocalFile)
	{
		global $NM_DIR;
		
		// Testa a existência do arquivo temporário
		if ( !$LocalFile || !is_file($LocalFile) || !is_readable($LocalFile) )
		{
			trigger_error(static::Language()->CantFind);
			return false;
		}
		
		// Lê o arquivo temporário para a memória e o apaga do disco
		$Browscap = file($LocalFile);
		unlink($LocalFile);
		
		// Conterá a última seção processada
		$LastSection = null;
		
		// Conterá as informações de versão do `php_browscap.ini`
		$BrowscapVersion = array();
		
		// Conterá as informações padrões dos navegadores
		$BrowscapDefaults = array();
		
		// Conterá o `php_browscap.ini` processado
		$BrowscapParsed = array();
		
		// Processando o arquivo...
		while ( $Line = array_shift($Browscap) )
		{
			// Garante que o script não será interrompido
			set_time_limit(100);
			
			// Um token: `;` e `[` são especiais
			$FirstChar = substr($Line, 0, 1);
			
			// Ignora linhas em branco
			if ( strlen(trim($Line)) <=0 )
			{
				continue;
			}
			
			// Ignora comentários (linhas iniciadas por `;`)
			if ( $FirstChar ==';' )
			{
				continue;
			}
			
			// Registra uma seção do arquivo ini
			if ( $FirstChar =='[' )
			{
				$LastSection = trim($Line, "[]\r\n");
				
				// Troca os curingas da seção por curingas do REGEX
				$LastSection = str_replace('[', '\\[', $LastSection);
				$LastSection = str_replace('{', '\\{', $LastSection);
				$LastSection = str_replace('(', '\\(', $LastSection);
				$LastSection = str_replace(']', '\\]', $LastSection);
				$LastSection = str_replace('}', '\\}', $LastSection);
				$LastSection = str_replace(')', '\\)', $LastSection);
				$LastSection = str_replace('|', '\\|', $LastSection);
				$LastSection = str_replace('.', '\\.', $LastSection);
				$LastSection = str_replace('+', '\\+', $LastSection);
				$LastSection = str_replace('^', '\\^', $LastSection);
				$LastSection = str_replace('$', '\\$', $LastSection);
				$LastSection = str_replace('*', '.*', $LastSection);
				$LastSection = str_replace('?', '.?', $LastSection);
				
				// Não há mais nada a processar para a seção. Continua.
				continue;
			}
			
			/**
			 * NOTA: A partir daqui são realizados os testes de seção
			 * e processadas as propriedades para dentro de suas
			 * respectivas matrizes.
			 **/
			
			// Separa as informações do arquivo `php_browscap.ini`
			if ( $LastSection == 'GJK_Browscap_Version' )
			{
				$Line = explode('=', $Line);
				$BrowscapVersion[strtolower($Line[0])] = trim($Line[1], "\"\r\n\'");
			}
			
			// Separa as propriedades padrões dos navegadores
			else if ( $LastSection == 'DefaultProperties' )
			{
				$Line = explode('=', $Line);
				$BrowscapDefaults[strtolower($Line[0])] = trim($Line[1], "\"\r\n\'");
			}
			
			// Armazena as configurações do navegador em sua seção
			else
			{
				$Line = explode('=', $Line);
				
				if ( !isset($BrowscapParsed[$LastSection]) || !is_array($BrowscapParsed[$LastSection]) )
				{
					$BrowscapParsed[$LastSection] = array();
				}
				
				$BrowscapParsed[$LastSection][strtolower($Line[0])] = trim($Line[1], "\"\r\n\'");
			}
			
			// Limpeza de memória
			unset($Line);
		}
		
		// Ordena as seções dos navegadores (quanto mais detalhes, mais importante)
		uksort($BrowscapParsed, array(get_called_class(), 'BrowscapSectionSort'));
		
		// Prepara o conteúdo que será armazenado em cache
		$NewBrowscap = array('Version' =>$BrowscapVersion, 'Defaults' =>$BrowscapDefaults, 'Browsers' =>$BrowscapParsed);
		
		// Grava o cache no disco (tmp/nmBrowscap.cache)
		$Parsed = file_put_contents($NM_DIR->tmp .DS .'nmBrowscap.cache', serialize($NewBrowscap));
		touch($NM_DIR->tmp .DS .'nmBrowscap.cache');
		
		// Elimina variáveis que não devem ser logadas. Limpeza de memória.
		unset($Browscap, $BrowscapDefaults, $BrowscapParsed, $BrowscapVersion, $NewBrowscap);
		
		if ( !$Parsed )
		{
			// Houve erro ao gravar o cache
			trigger_error(static::Language()->ParseError);
			return false;
		}
		
		else
		{
			// Sucesso
			return true;
		}
	}
	
	/**
	 * Ordena as seções do arquivo temporário `php_browscap.ini`
	 * 
	 * NOTA: Este método é executado internamente.
	 * 
	 * @static
	 * @access   private
	 * @param    string   $A - Uma seção a ser avaliada
	 * @param    string   $B - Uma seção a ser avaliada
	 * @return   int      A ordem que `$A` deve tomar em relação a `$B`. 0: empate, +1: depois, -1: antes.
	 **/
	static
	private
	function BrowscapSectionSort( $A, $B )
	{
		// Garante que o script não será interrompido
		set_time_limit(100);
		
		// Empate
		if ( strlen($A) ==strlen($B) )
		{
			return 0;
		}
		
		else
		{
			// Prioridade
			if ( strlen($A) >strlen($B) )
			{
				return -1;
			}
			
			// Inferioridade
			else
			{
				return +1;
			}
		}
	}
	
	/**
	 * Retorna as configurações padrões
	 * 
	 * @see nmGear::DefaultConfiguration()
	 **/
	static
	public
	function DefaultConfiguration( $Context ='default' )
	{
		$Configuration  = '<Configuration>';
		$Configuration .= '   <RemoteFile>http://browsers.garykeith.com/stream.asp?PHP_BrowsCapINI</RemoteFile>';
		$Configuration .= '   <RemoteVersion>http://updates.browserproject.com/version-number.asp</RemoteVersion>';
		$Configuration .= '   <UpdateInterval>604800</UpdateInterval>';
		$Configuration .= '   <UseCoreBrowscap>1</UseCoreBrowscap>';
		$Configuration .= '</Configuration>';
		
		return new SimpleXMLElement($Configuration);
	}
	
	/**
	 * Retorna as linguagens padrões
	 * 
	 * @see nmGear::DefaultLanguage()
	 **/
	static
	public
	function DefaultLanguage( $Context ='default', $Language ='pt', $Family ='br' )
	{
		$Languages = array();
		$Language  = strtolower($Language);
		$Family    = strtolower($Family);
		
		// Portugês do Brasil
		$LanguageXML  = '<Language>';
		$LanguageXML .= '   <CantDownload>Não foi possível adquirir o arquivo `browscap.ini` do servidor remoto.</CantDownload>';
		$LanguageXML .= '   <CantFind>Não foi possível localizar o arquivo `browscap.ini` no servidor local.</CantFind>';
		$LanguageXML .= '   <ParseError>Não foi possível processar o arquivo `browscap.ini`.</ParseError>';
		$LanguageXML .= '   <nmBrowscap>nmBrowscap: Detecção do navegador realizada pela biblioteca `nmBrowscap`.</nmBrowscap>';
		$LanguageXML .= '   <get_browser>nmBrowscap: Detecção do navegador realizada pela função nativa `get_browser()`.</get_browser>';
		$LanguageXML .= '</Language>';
		
		$Languages['pt_br'] = new SimpleXMLElement($LanguageXML);
		
		// Inglês
		$LanguageXML  = '<Language>';
		$LanguageXML .= '   <CantDownload>Can not download the file `browscap.ini` from a remote server.</CantDownload>';
		$LanguageXML .= '   <CantFind>Can not find the file `browscap.ini` in the local server.</CantFind>';
		$LanguageXML .= '   <ParseError>Can not parse the file `browscap.ini`.</ParseError>';
		$LanguageXML .= '   <nmBrowscap>nmBrowscap: Browser detected through the library `nmBrowscap`.</nmBrowscap>';
		$LanguageXML .= '   <get_browser>nmBrowscap: Browser detected through the native function `get_browser()`.</get_browser>';
		$LanguageXML .= '</Language>';
		
		$Languages['en']    = 
		$Languages['en_gb'] = 
		$Languages['en_us'] = new SimpleXMLElement($LanguageXML);
		
		if ( empty($Family) )
		{
			$LanguageCode = $Language;
		}
		
		else
		{
			$LanguageCode = "{$Language}_{$Family}";
		}
		
		if ( isset($Languages[$LanguageCode]) )
		{
			return $Languages[$LanguageCode];
		}
		
		else if ( isset($Languages[$Language]) )
		{
			trigger_error("nmBrowscap: Não foi possível adquirir a linguagem '{$LanguageCode}'. Utilizado '{$Language}'.");
			return $Languages[$Language];
		}
		
		else
		{
			trigger_error("nmBrowscap: Não foi possível adquirir a linguagem '{$LanguageCode}'. Utilizado 'pt_br'.");
			return $Languages['pt_br'];
		}
	}
}

?>
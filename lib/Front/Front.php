<?php
/*
 * (c) 2012 Damien Corpataux
 *
 * LICENSE
 * This library is licensed under the GNU GPL v3.0 license,
 * accessible at http://www.gnu.org/licenses/gpl-3.0.html
 *
**/

/**
 * Front controller base class.
 *
 * Responsibilities
 * - decorate controllers
 * @package xFreemwork
**/
abstract class xFront extends xRestElement {

    /**
     * HTTP Request information.
     * @var array
     */
    var $http = array(
        'method' => null,
    );

    /**
     * 'HTTP Request Method' => 'xFront Method' mapping.
     * Since REST specification is subject to interpretation
     * when it comes to HTTP Method to CRUD mapping,
     * this mapping array enables to define which
     * xFront method should be called for each HTTP Request Method.
     * @var array
     */
    var $method_mapping = array(
        'GET' => 'get',
        'POST' => 'post',
        'PUT' => 'put',
        'DELETE' => 'delete'
    );

    protected function __construct($params = null) {
        parent::__construct($params);
        $this->setup_http_request_information();
        $this->setup_i18n();
    }

    /**
     * Sets up the HTTP request information array.
     * Default method: GET (if REQUEST_METHOD is undefined).
     */
    function setup_http_request_information() {
        $method = @strtoupper($_SERVER['REQUEST_METHOD']);
        if (!$method) $method = 'GET';
        $this->http['method'] = $method;
    }

    /**
     * Sets up the Gettext locale and domain according the selected/guessed language.
     * @see xBootstrap::setup_i18n()
     */
    function setup_i18n() {
        // Skips i18n setup if Gettext is not installed
        if (!function_exists('gettext')) return;
        // Defines the current language
        $lang_aliases = @xContext::$config->i18n->lang->alias;
        $lang_available = $lang_aliases ? $lang_aliases->toArray() : array();
        $lang_browser = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
        // If a language is given  and is available, use it
        if (array_key_exists(@$this->params['xlang'], $lang_available)) {
            $lang = $this->params['xlang'];
        // Else, if the session stored language is available, use it
        } elseif (array_key_exists(@$_SESSION['x']['lang'], $lang_available)) {
            $lang = $_SESSION['x']['lang'];
        // Else, use the browser language if it is available
        } elseif (array_key_exists($lang_browser, $lang_available)) {
            $lang = $lang_browser;
        // Else use the default language specified in config
        } else {
            // xContext::$lang default value is set by xBootstrap
            $lang = xContext::$lang;
        }
        $_SESSION['x']['lang'] = xContext::$lang = $lang;
        // Sets up gettext
        $directory = xContext::$basepath.'/i18n/mo';
        $locale = @$lang_available[$lang]; // Warning: must the exact locale as defined on the linux host,
        $domain = $lang;
        xContext::$log->log("Setting up gettext for '{$lang}' language, using '{$locale}' locale and '{$domain}' domain", $this);
        $success = min(
            setlocale(LC_MESSAGES, $locale),
            // putenv is necessary for Windows OS
            putenv("LANG={$locale}"),
            @bindtextdomain($domain, $directory),
            textdomain($domain),
            bind_textdomain_codeset($domain, 'UTF-8')
        );
        if (!$success) {
            xContext::$log->warning("Failed setting up gettext for '{$lang}'", $this);
        }
    }

    /**
     * Entry point for Front controller.
     * This method call the method related
     * to the HTTP status.
     * This is part of the REST orientatin of the framework.
     * @return mixed
     */
    function handle() {
        $front_method = $this->method_mapping[$this->http['method']];
        if (!$front_method)throw new xException('Method not allowed', 405);
        $handle_method = "handle_{$front_method}";
        return $this->$handle_method();
    }

    function handle_get() { return $this->get(); }
    function handle_post() { return $this->post(); }
    function handle_put() { return $this->put(); }
    function handle_delete() { return $this->delete(); }

    /**
     * Returns an error message in case of an error.
     * @return string The error message to output.
     */
    abstract function handle_error($exception);

    /**
     * Returns HTTP request body contents
     * @return string
     */
    static function get_request_body() {
        return file_get_contents('php://input');
    }

    /**
     * Loads and returns the specified front element.
     * For example, the following code will
     * load the fronts/web.php file.
     * and return an instance of the WebFront class:
     * <code>
     * xFront::load('web');
     * </code>
     * @param string The front to load.
     * @return xFront
     */
    static function load($name, $params = null) {
        $files = array(
            "{$name}Front" => xContext::$basepath."/fronts/{$name}.php",
            "x{$name}Front" => xContext::$libpath.'/Front/'.ucfirst($name).'Front.php'
        );
        return self::load_these($files, $params);
    }
}
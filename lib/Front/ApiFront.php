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
 * Front controller class, api flavour.
 * Implements API features and session initialization.
 * @package xFreemwork
 * @todo Implement API Key mechanism.
**/
class xApiFront extends xRestFront {

    function __construct($params = null) {
        // TODO: check for API key?
        if (!session_id()) {
            if (!@$params['key']) {
                session_start();
            } else {
                // TODO: check this code
                session_id($params['key']);
                session_start();
            }
        }
        parent::__construct($params);
    }

    /**
     * Calls a controller method and return its result.
     * @return mixed Controller result.
     */
    function call_method() {
        if (!@$this->params['xcontroller']) throw new xException('Controller param missing', 400);
        if (!@$this->params['xmethod']) throw new xException('Method param missing', 400);
        $method = str_replace('-', '', $this->params['xmethod']);
        if ($method{0} == '_') throw new xException("Method {$method} is not meant to be called", 401);
        $controller_name = $this->params['xcontroller'];
        xContext::$log->log("Creating controller {$controller_name}", $this);
        if (@$this->params['xmodule']) $controller_name = "{$this->params['xmodule']}/$controller_name";
        $controller = xController::load($controller_name, $this->params);
        xContext::$log->log("Calling {$controller_name}->{$method}() method", $this);
        if (!method_exists($controller, $method)) throw new xException("Controller method not found: {$method}", 400);
        return $controller->$method();
    }

    function get() {
        print $this->encode($this->call_method());
    }

    function post() {
        return $this->get();
    }
}
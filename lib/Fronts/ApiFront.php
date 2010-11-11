<?php
/*
 * (c) 2010 Damien Corpataux
 *
 * LICENSE
 * This library is licensed under the GNU GPL v3.0 license,
 * accessible at http://www.gnu.org/licenses/gpl-3.0.html
 *
**/

/**
 * Front controller class, api flavour.
 * Implements business objects logic,
 * such as validations, authentication, etc.
 * @package xFreemwork
**/
class xApiFront extends xRestFront {

    // TODO: shall the role check be done here or in controller->method() ?
    /**
     * Contains allowed methods for each role.
     * Conventional array structure:
     * <code>
     * array(
     *     'role1' => array(
     *         'controller1' => array('method1', 'method2'),
     *         'controller2' => array('method1'),
     *         // Allows every method of controller2 for role1
     *         'controller2' => '*'
     *     ),
     *     // Allows every controller and every methods for role2
     *     'role2' =>'*'
     * )
     * </code>
     * @var array
     */
    var $security = array();

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

    function call_method() {
        if (!@$this->params['xmethod']) throw new xException('Method param missing', 400);
        $method = $this->params['xmethod'];
        $method = str_replace('-', '', $method);
        if ($method{0} == '_') throw new xException("Method {$method} is not meant to be called", 401);
        xContext::$log->log("Creating controller {$this->params['xcontroller']}", $this);
        $controller_name = $this->params['xcontroller'];
        if (@$this->params['xmodule']) $controller_name = "{$this->params['xmodule']}/$controller_name";
        $controller = xController::load($controller_name, $this->params);
        xContext::$log->log("Calling {$this->params['xcontroller']}->{$method}() method", $this);
        if (!method_exists($controller, $method)) throw new xException("Controller method not found: {$method}", 400);
        return $controller->$method();
    }

    function get() {
        print $this->encode($this->call_method());
    }

    function post() {
        $this->get();
    }

    function put() {
        throw new xException('Not implemented', 501);
    }

    function delete() {
        throw new xException('Not implemented', 501);
    }
}
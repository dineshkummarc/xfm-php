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
 * Base view class.
 *
 * Responsibilities
 * - deals with rendering and internationalization (i18n)
 * @package xFreemwork
**/
class xView {

    /**
     * Template data (associative array).
     * This data will be made available inside the template file.
     * The conventional array structure is as follows:
     * <code>
     * array(
     *     items => array(
     *         array(id => '0', what => 'Items cell contains the associative array'),
     *         array(id => '1', what => 'of data to display, usually retrieved'),
     *         array(id => '2', what => 'from the database.')
     *     )
     *     html => array(
     *         // Arbitrary HTML code to insert in the view
     *         myview => 'HTML code generated by some other view, i.e. myview'
     *     ),
     *     messages => array(
     *         'failed' => true
     *     ),
     *     form => array(
     *         // Typically: form data to latch, i.e. the value of the name input of the posted form
     *         name => 'The value for the `name` field of the form inside the view',
     *         user => 'The value for the `user` field of the form inside the view'
     *     ),
     *     invalids => array(
     *         // Typically: to be displayed form validation error message for each invalid form field
     *         name => 'The error message for the `name`field, e.g. The name is too short',
     *         user => 'e.g. This username is already taken'
     *     ),
     *     paging => array(
     *         // Paging context informations
     *         current => 'The current page of the paging context',
     *         first => 'The first page number of the paging context',
     *         last => 'The last page number of the paging context',
     *         total => 'The total number of results of the paging context'
     *     ),
     *     misc => array(
     *         // Whatever miscellaneous, view specific information
     *         somekey => 'some value'
     *     )
     * )
     * </code>
     * @var array
     */
    var $data = array();

    /**
     * Metadata for the view (associative array).
     * Used for building html head metadata and links (css and javascript).
     * The conventional array structure is as follows:
     * <code>
     * array(
     *     title => 'Some title',
     *     keywords => 'html,meta,keywords,comma,separated',
     *     css => array(
     *         'css/main.css',
     *         array('css/print.css', 'print')
     *     ),
     *     js => array(
     *         'js/file.js'
     *     ),
     *     related => array(
     *         content_id_1 => 'Some HTML content to be displayed in the layout'
     *     ),
     *     navigation => array(
     *         highlight => 'Id of the main navigation item to highligh'
     *     )
     * )
     * </code>
     * @var array
     */
    var $meta = array();

    /**
     * The View subclass directory.
     * Necessary because PHP is not yet able to provide with the directory
     * of a subclass within the parent class code.
     * @var string
     */
    var $path;

    /**
     * Computed view tpl name, used for rendering
     * the default template if view PHP class does not exist.
     * @see xView::render()
     * @var string
     */
    var $default_tpl;

    /**
     * A buffer for buffering view output.
     * @var string
     */
    var $buffer;

    /**
     * An optionnal view to be used to embed this view.
     * Must the name of a view (as defined in @see xView::load()).
     * @var string
     */
    var $container = null;

    /**
     * View classes can only be instanciated through the View::load() method.
     * @param array An array of data to be merged to the view instance
     * @see View::load()
     */
    protected function __construct($data = null) {
        if (!is_null($data)) $this->data = xUtil::array_merge($this->data, $data);
        xContext::$log->log(get_class($this)." created", $this);
        $this->init();
    }

    /**
     * Hook for subclass initialization logic
     */
    protected function init() {}

    /**
     * Loads and returns the view specified object.
     * For example, the following code will
     * load the views/entry/item.php file.
     * and return an instance of the EntryItemView class:
     * <code>
     * xView::load('entry/item');
     * </code>
     * @param string The view to load.
     * @param array An array of data to be merged to the view instance
     * @param array An array of metadata on which the view metadata will be merged
     * @return xView
     */
    static function load($name, $data = null, &$meta_return = null) {
        $file = xContext::$basepath."/views/{$name}.php";
        xContext::$log->log("Loading view: $file", 'xView');
        if (file_exists($file)) {
            require_once($file);
            $class_name = str_replace(array('/', '.', '-'), '', $name."View");
            xContext::$log->log(array("Instanciating view: $class_name"), 'xView');
            $instance = new $class_name($data);
        } else {
            $instance = new xView($data);
        }
        // Computes view basepath
        $parts = explode('/', $name);
        array_pop($parts);
        $instance->path = xContext::$basepath."/views/".implode('/', $parts);
        $instance->default_tpl = array_pop(explode('/', $name)).'.tpl';
        // Merges view meta with the given array
        if (is_array($meta_return)) {
            xContext::$log->log(array("xView::load(): merging view meta into given array"), 'xView');
            $meta_return = xUtil::array_merge($meta_return, $instance->meta);
        }
        return $instance;
    }

    /**
     * Convenience method for merging this instance metadata
     * with additional metadata.
     * The given metadata will erase this instance metadata.
     * The merged metadata array replaces this instance metadata
     * and is also returned.
     * @param array Metadata array.
     * @return array Merged metadata array.
     */
    function add_meta($meta) {
        return $this->meta = xUtil::array_merge($this->meta, $meta);
    }

    /**
     * Convenience method for merging this instance data
     * with additional data.
     * The given data will erase this instance data.
     * The merged data array replaces this instance data
     * and is also returned.
     * @param array data array.
     * @return array Merged data array.
     */
    function add_data($data) {
        return $this->data = xUtil::array_merge($this->data, $data);
    }

    /**
     * Renders the given template with the given data.
     * @param string $template The filename of the template to use
              (e.g. tplfile.tpl).
     * @param mixed $data The data to be used within the template context.
              (defaults to instance data property).
     * @return string
     */
    function apply($template, $data = null, $meta = null) {
        // Disables notices reporting in php template code
        $error_reporting = error_reporting();
        error_reporting(E_ALL ^ E_NOTICE);
        // Create template-wide variables and functions
        $d = xUtil::array_merge($this->data, $data);
        $m = xUtil::array_merge($this->meta, $meta);
        if (!function_exists('u')) {
            function u($path = null, $full = false) {
                return xUtil::url($path, $full);
            }
        }
        // Loads the template and processes template tags
        $file = "{$this->path}/{$template}";
        if (!file_exists($file)) throw new xException("Template file not found ($file)", 404);
//        $template = $this->process_tags(file_get_contents($file));
        // Renders the template
        // TODO: Clean spaces before '<?php' and after '>'
        ob_start();
        require($file);
        $s = ob_get_contents();
        ob_end_clean();
        // Reverts error reporting level
        error_reporting($error_reporting);
        // Embeds the template if applicable
        if ($this->container) {
            $container_view = xView::load($this->container, array(
                'contents' => $s,
                'misc' => @$d['container']
            ), $this->meta);
            $s = $container_view->render();
        }
        return $s;
    }

/*
    function process_tags($template) {
        // Processes {{ ... }}
        $template = preg_replace_callback('/\{(.*?)\}/', array($this, "process_tag_var"), $template);
        return $template;
    }
    function process_tag_var($matches) {
        $var = $matches[1];
        // Create PHP variable
        $phpvar = '$d';
        foreach (explode('.', $var) as $fragment) $phpvar .= "['{$fragment}']";
        // Replaces tag with PHP snippet
        return "<?php print @{$phpvar} ?>";
    }
*/

    /**
     * Buffers the given string in the view buffer.
     * @see xView::buffer
     * @param string|ignored $string The string to buffer.
     */
    function buffer($string) {
        $this->buffer .= (string)$string;
    }

    /**
     * Shorthand method for {@link buffer()}.
     * @param string|ignored $string The string to buffer.
     */
    function b($string) {
        $this->buffer .= (string)$string;
    }

    /**
     * Retrun rendered view.
     * This method should be overridden in subclasses to
     * override the default tpl rendering.
     * @return string
     */
    function render() {
        return $this->apply($this->default_tpl);
    }
}
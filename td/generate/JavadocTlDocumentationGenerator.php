<?php

require_once 'TlDocumentationGenerator.php';

class JavadocTlDocumentationGenerator extends TlDocumentationGenerator
{
    private $nullable_type;
    private $nullable_annotation;
    private $java_version;

    protected function escapeDocumentation($doc)
    {
        $doc = htmlspecialchars($doc);
        $doc = str_replace('*/', '*&#47;', $doc);
        $doc = preg_replace_callback('/_([A-Za-z])/', function ($matches) {return strtoupper($matches[1]);}, $doc);
        return $doc;
    }

    protected function getFieldName($name)
    {
        if (substr($name, 0, 6) === 'param_') {
            $name = substr($name, 6);
        }
        return preg_replace_callback('/_([A-Za-z])/', function ($matches) {return strtoupper($matches[1]);}, trim($name));
    }

    protected function getClassName($type)
    {
        return implode(array_map(ucfirst, explode('.', trim($type, "\n ;"))));
    }

    protected function getTypeName($type)
    {
        switch ($type) {
            case 'Bool':
                return 'boolean';
            case 'int32':
                return 'int';
            case 'int53':
            case 'int64':
                return 'long';
            case 'double':
                return $type;
            case 'string':
                return 'String';
            case 'bytes':
                return 'byte[]';
            case 'bool':
            case 'int':
            case 'long':
            case 'Int':
            case 'Long':
            case 'Int32':
            case 'Int53':
            case 'Int64':
            case 'Double':
            case 'String':
            case 'Bytes':
                $this->printError("Wrong type $type");
                return '';
            default:
                if (substr($type, 0, 6) === 'vector') {
                    if ($type[6] !== '<' || $type[strlen($type) - 1] !== '>') {
                        $this->printError("Wrong vector subtype in $type");
                        return '';
                    }
                    return $this->getTypeName(substr($type, 7, -1)).'[]';
                }

                if (preg_match('/[^A-Za-z0-9.]/', $type)) {
                    $this->printError("Wrong type $type");
                    return '';
                }
                return $this->getClassName($type);
        }
    }

    protected function getBaseClassName($is_function)
    {
        return $is_function ? 'Function' : 'Object';
    }

    protected function needRemoveLine($line)
    {
        return strpos(trim($line), '/**') === 0 || strpos(trim($line), '*') === 0 ||
            ($this->nullable_type && strpos($line, $this->nullable_type) > 0);
    }

    protected function needSkipLine($line)
    {
        $line = trim($line);
        return strpos($line, 'public') !== 0 && !($this->nullable_type && $line == 'import java.util.Arrays;') &&
            !$this->isHeaderLine($line);
    }

    protected function isHeaderLine($line)
    {
        return trim($line) === '@Override';
    }

    protected function extractClassName($line)
    {
        if (strpos($line, 'public static class ') > 0) {
            return explode(' ', trim($line))[3];
        }
        return '';
    }

    protected function fixLine($line)
    {
        if (strpos($line, 'CONSTRUCTOR = ') > 0) {
            return substr($line, 0, strpos($line, '='));
        }

        return $this->nullable_annotation ? str_replace($this->nullable_annotation.' ', '', $line) : $line;
    }

    protected function addGlobalDocumentation()
    {
        $this->addDocumentation('public class TdApi {', <<<EOT
/**
 * This class contains as static nested classes all other TDLib interface
 * type-classes and function-classes.
 * <p>
 * It has no inner classes, functions or public members.
 */
EOT
);

        $this->addDocumentation('    public abstract static class Object {', <<<EOT
    /**
     * This class is a base class for all TDLib interface classes.
     */
EOT
);

        $this->addDocumentation('        public abstract int getConstructor();', <<<EOT
        /**
         * @return identifier uniquely determining type of the object.
         */
EOT
);

        $this->addDocumentation('        public String toString() {', <<<EOT
        /**
         * @return string representation of the object.
         */
EOT
);

        $this->addDocumentation('    public abstract static class Function extends Object {', <<<EOT
    /**
     * This class is a base class for all TDLib interface function-classes.
     */
EOT
);

        $this->addDocumentation('        public static final int CONSTRUCTOR', <<<EOT
        /**
         * Identifier uniquely determining type of the object.
         */
EOT
);

        $this->addDocumentation('        public int getConstructor() {', <<<EOT
        /**
         * @return this.CONSTRUCTOR
         */
EOT
);

        if ($this->nullable_type) {
            $import = 'import java.util.Arrays;';
            $this->addDocumentation($import, '');
            $this->addLineReplacement($import, "import $this->nullable_type;\n$import\n");
        }
    }

    protected function addAbstractClassDocumentation($class_name, $documentation)
    {
        $this->addDocumentation("    public abstract static class $class_name extends Object {", <<<EOT
    /**
     * This class is an abstract base class.
     * $documentation
     */
EOT
);
    }

    protected function addClassDocumentation($class_name, $base_class_name, $description, $return_type)
    {
        $return_type_description = $return_type ? "\n     *\n     * <p> Returns {@link $return_type $return_type} </p>" : '';

        $this->addDocumentation("    public static class $class_name extends $base_class_name {", <<<EOT
    /**
     * $description$return_type_description
     */
EOT
);
    }

    protected function addFieldDocumentation($class_name, $field_name, $type_name, $field_info, $may_be_null)
    {
        $full_line = $class_name."        public $type_name $field_name;";
        $this->addDocumentation($full_line, <<<EOT
        /**
         * $field_info
         */
EOT
);
        if ($may_be_null && $this->nullable_annotation && ($this->java_version >= 8 || substr($type_name, -1) != ']')) {
            $this->addLineReplacement($full_line, "        public $this->nullable_annotation $type_name $field_name;\n");
        }
    }

    protected function addDefaultConstructorDocumentation($class_name)
    {
        $this->addDocumentation("        public $class_name() {", <<<EOT
        /**
         * Default constructor.
         */
EOT
);
    }

    protected function addFullConstructorDocumentation($class_name, $known_fields, $info)
    {
        $explicit = count($known_fields) == 1 ? 'explicit ' : '';
        $full_constructor = "        public $class_name(";
        $colon = '';
        foreach ($known_fields as $name => $type) {
            $full_constructor .= $colon.$this->getTypeName($type).' '.$this->getFieldName($name);
            $colon = ', ';
        }
        $full_constructor .= ') {';

        $full_doc = <<<EOT
        /**
         * Constructor for initialization of all fields.
         *

EOT;
        foreach ($known_fields as $name => $type) {
            $full_doc .= '         * @param '.$this->getFieldName($name).' '.$info[$name]."\n";
        }
        $full_doc .= '         */';
        $this->addDocumentation($full_constructor, $full_doc);
    }

    public function __construct($nullable_type, $nullable_annotation, $java_version) {
        $this->nullable_type = trim($nullable_type);
        $this->nullable_annotation = trim($nullable_annotation);
        $this->java_version = intval($java_version);
    }
}

$nullable_type = isset($argv[3]) ? $argv[3] : '';
$nullable_annotation = isset($argv[4]) ? $argv[4] : '';
$java_version = isset($argv[5]) ? intval($argv[5]) : 7;

$generator = new JavadocTlDocumentationGenerator($nullable_type, $nullable_annotation, $java_version);
$generator->generate($argv[1], $argv[2]);

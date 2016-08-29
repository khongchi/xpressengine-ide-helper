<?php
/**
 * Laravel IDE Helper Generator
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2014 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace Barryvdh\LaravelIdeHelper;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Context;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tag\MethodTag as DocBlockMethodTag;
use phpDocumentor\Reflection\DocBlock\Tag\ReturnTag;
use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;
use phpDocumentor\Reflection\DocBlock\Serializer as DocBlockSerializer;

class MethodTag implements MethodInterface
{
    /** @var \phpDocumentor\Reflection\DocBlock  */
    protected $phpdoc;

    /** @var DocBlockMethodTag  */
    protected $method;

    protected $output = '';
    protected $name;
    protected $namespace;
    protected $params = array();
    protected $params_with_default = array();
    protected $interfaces = array();
    protected $return = null;

    /**
     * @param DocBlockMethodTag $methodtag
     * @param string            $alias
     * @param \ReflectionClass  $class
     * @param string|null       $methodName
     * @param array             $interfaces
     */
    public function __construct(DocBlockMethodTag $methodtag, $alias, $class, $methodName = null, $interfaces = array())
    {
        $this->method = $methodtag;
        $this->interfaces = $interfaces;
        $this->name = $methodName ?: $methodtag->name;

        $this->namespace = $class->getNamespaceName();

        //Create a DocBlock and serializer instance
        //$this->phpdoc = new DocBlock($methodtag, new Context($this->namespace));

        //Normalize the description and inherit the docs from parents/interfaces
        try {
            //$this->normalizeParams($this->phpdoc);
            $this->normalizeReturn($methodtag);
            //$this->normalizeDescription($methodtag);
        } catch (\Exception $e) {}

        //Get the parameters, including formatted default values
        $this->getParameters($methodtag);

        //Make the method static
        //$this->phpdoc->appendTag(Tag::createInstance('@static', $this->phpdoc));

        //Reference the 'real' function in the declaringclass
        $declaringClass = $class;
        $this->declaringClassName = '\\' . ltrim($declaringClass->name, '\\');
        $this->root = '\\' . ltrim($class->getName(), '\\');
    }

    /**
     * Get the class wherein the function resides
     *
     * @return string
     */
    public function getDeclaringClass()
    {
        return $this->declaringClassName;
    }

    /**
     * Return the class from which this function would be called
     *
     * @return string
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Get the docblock for this method
     *
     * @param string $prefix
     * @return mixed
     */
    public function getDocComment($prefix = "\t\t")
    {
        $syntax = [
            $prefix.'/**',
            $prefix.' * ',
            $prefix." */
            "
        ];

        $ret = [];
        $ret[] = $syntax[0];
        $ret[] = $syntax[1].$this->method->getDescription();
        $ret[] = $syntax[1];
        $ret[] = $syntax[1].'@return '.$this->return;
        $ret[] = $syntax[2];

        return implode(PHP_EOL, $ret);
    }

    /**
     * Get the method name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the parameters for this method
     *
     * @param bool $implode Wether to implode the array or not
     * @return string
     */
    public function getParams($implode = true)
    {
        return $implode ? implode(', ', $this->params) : $this->params;
    }

    /**
     * Get the parameters for this method including default values
     *
     * @param bool $implode Wether to implode the array or not
     * @return string
     */
    public function getParamsWithDefault($implode = true)
    {
        return $implode ? implode(', ', $this->params_with_default) : $this->params_with_default;
    }

    ///**
    // * Get the description and get the inherited docs.
    // *
    // * @param DocBlock $methodTag
    // */
    //protected function normalizeDescription(DocBlockMethodTag $methodTag)
    //{
    //    //Get the short + long description from the DocBlock
    //    $description = $methodTag->getDescription();
    //
    //    //Loop through parents/interfaces, to fill in {@inheritdoc}
    //    if (strpos($description, '{@inheritdoc}') !== false) {
    //        $inheritdoc = $this->getInheritDoc($this->method);
    //        $inheritDescription = $inheritdoc->getText();
    //
    //        $description = str_replace('{@inheritdoc}', $inheritDescription, $description);
    //        $methodTag->setText($description);
    //
    //        $this->normalizeParams($inheritdoc);
    //        $this->normalizeReturn($inheritdoc);
    //
    //        //Add the tags that are inherited
    //        $inheritTags = $inheritdoc->getTags();
    //        if ($inheritTags) {
    //            /** @var Tag $tag */
    //            foreach ($inheritTags as $tag) {
    //                $tag->setDocBlock();
    //                $methodTag->appendTag($tag);
    //            }
    //        }
    //    }
    //}

    ///**
    // * Normalize the parameters
    // *
    // * @param DocBlock $phpdoc
    // */
    //protected function normalizeParams(DocBlockMethodTag $methodTag)
    //{
    //    //Get the return type and adjust them for beter autocomplete
    //    $paramTags = $methodTag->getArguments();
    //    if ($paramTags) {
    //        /** @var ParamTag $tag */
    //        foreach($paramTags as $tag){
    //            // Convert the keywords
    //            $content = $this->convertKeywords($tag->getContent());
    //            $tag->setContent($content);
    //
    //            // Get the expanded type and re-set the content
    //            $content = $tag->getType() . ' ' . $tag->getVariableName() . ' ' . $tag->getDescription();
    //            $tag->setContent(trim($content));
    //        }
    //    }
    //}

    /**
     * Normalize the return tag (make full namespace, replace interfaces)
     *
     * @param DocBlock $methodtag
     */
    protected function normalizeReturn(DocBlockMethodTag $methodtag)
    {
        //Get the return type and adjust them for beter autocomplete
        $returnTag = $methodtag->getType();
        if ($returnTag) {
            $this->return = $returnTag;
        }else{
            $this->return = null;
        }
    }

    ///**
    // * Convert keywwords that are incorrect.
    // *
    // * @param  string $string
    // * @return string
    // */
    //protected function convertKeywords($string)
    //{
    //    $string = str_replace('\Closure', 'Closure', $string);
    //    $string = str_replace('Closure', '\Closure', $string);
    //    $string = str_replace('dynamic', 'mixed', $string);
    //
    //    return $string;
    //}

    /**
     * Should the function return a value?
     *
     * @return bool
     */
    public function shouldReturn()
    {
        if($this->return !== "void" && $this->name !== "__construct"){
            return true;
        }

        return false;
    }

    /**
     * Get the parameters and format them correctly
     *
     * @param DocBlockMethodTag $method
     * @return array
     */
    public function getParameters($method)
    {
        //Loop through the default values for paremeters, and make the correct output string
        $params = array();
        $paramsWithDefault = array();
        foreach ($method->getArguments() as $param) {
            $params[] = $param[0];
            $paramsWithDefault[] = implode(' ', $param);
        }

        $this->params = $params;
        $this->params_with_default = $paramsWithDefault;
    }

    ///**
    // * @param \ReflectionMethod $reflectionMethod
    // * @return DocBlock
    // */
    //protected function getInheritDoc($reflectionMethod)
    //{
    //    $parentClass = $reflectionMethod->getDeclaringClass()->getParentClass();
    //
    //    //Get either a parent or the interface
    //    if ($parentClass) {
    //        $method = $parentClass->getMethod($reflectionMethod->getName());
    //    } else {
    //        $method = $reflectionMethod->getPrototype();
    //    }
    //    if ($method) {
    //        $namespace = $method->getDeclaringClass()->getNamespaceName();
    //        $phpdoc = new DocBlock($method, new Context($namespace));
    //
    //        if (strpos($phpdoc->getText(), '{@inheritdoc}') !== false) {
    //            //Not at the end yet, try another parent/interface..
    //            return $this->getInheritDoc($method);
    //        } else {
    //            return $phpdoc;
    //        }
    //    }
    //}
}
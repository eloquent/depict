<?php

use Eloquent\Depict\InlineExporter;

class InlineExporterTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->subject = InlineExporter::create();

        $this->isHhvm = false !== strpos(phpversion(), 'hhvm');
    }

    public function exportData()
    {
        return array(
            'null'             => array(null,                                            'null'),
            'true'             => array(true,                                            'true'),
            'false'            => array(false,                                           'false'),
            '0'                => array(0,                                               '0'),
            '-0'               => array(-0,                                              '0'),
            '1'                => array(1,                                               '1'),
            '-1'               => array(-1,                                              '-1'),
            '0.0'              => array(0.0,                                             '0.000000e+0'),
            '-0.0'             => array(-0.0,                                            '0.000000e+0'),
            '1.0'              => array(1.0,                                             '1.000000e+0'),
            '-1.0'             => array(-1.0,                                            '-1.000000e+0'),
            'STDIN'            => array(STDIN,                                           'resource#1'),
            'STDOUT'           => array(STDOUT,                                          'resource#2'),
            'a\nb'             => array("a\nb",                                          '"a\nb"'),
            '[]'               => array(array(),                                         '#0[]'),
            '[1]'              => array(array(1),                                        '#0[1]'),
            '[1, 1]'           => array(array(1, 1),                                     '#0[1, 1]'),
            '[1: 1]'           => array(array(1 => 1),                                   '#0[1: 1]'),
            '[1: 1, 2: 2]'     => array(array(1 => 1, 2 => 2),                           '#0[1: 1, 2: 2]'),
            '[1, [1, 1]]'      => array(array(1, array(1, 1)),                           '#0[1, #1[1, 1]]'),
            '[[1, 1], [1, 1]]' => array(array(array(1, 1), array(1, 1)),                 '#0[#1[1, 1], #2[1, 1]]'),
            '{a: 0}'           => array((object) array('a' => 0),                        '#0{a: 0}'),
            '{a: 0, b: 1}'     => array((object) array('a' => 0, 'b' => 1),              '#0{a: 0, b: 1}'),
            '{a: {a: 0}}'      => array((object) array('a' => (object) array('a' => 0)), '#0{a: #1{a: 0}}'),
            '{a: []}'          => array((object) array('a' => array()),                  '#0{a: #0[]}'),
            'object'           => array(new TestClass(),                                 'TestClass#0{}'),
        );
    }

    /**
     * @dataProvider exportData
     */
    public function testExport($value, $expected)
    {
        $copy = $value;

        $this->assertSame($expected, $this->subject->export($value));
        $this->assertSame($copy, $value);
    }

    public function testExportMaxDepthWithArrays()
    {
        $depth0 = InlineExporter::create(array('depth' => 0));
        $depth1 = InlineExporter::create(array('depth' => 1));
        $depth2 = InlineExporter::create(array('depth' => 2));

        $array = array();
        $value = array(&$array, array(&$array));

        $this->assertSame('#0[:2]', $depth0->export($value, 0));
        $this->assertSame('#0[#1[], #2[:1]]', $depth1->export($value, 1));
        $this->assertSame('#0[#1[], #2[&1[]]]', $depth2->export($value, 2));
        $this->assertSame('#0[#1[], #2[&1[]]]', $this->subject->export($value));
    }

    public function testExportMaxDepthWithObjects()
    {
        $depth0 = InlineExporter::create(array('depth' => 0));
        $depth1 = InlineExporter::create(array('depth' => 1));
        $depth2 = InlineExporter::create(array('depth' => 2));

        $object = (object) array();
        $value = (object) array('a' => &$object, 'b' => (object) array('a' => &$object));

        $this->assertSame('#0{:2}', $depth0->export($value, 0));
        $this->assertSame('#0{a: #1{}, b: #2{:1}}', $depth1->export($value, 1));
        $this->assertSame('#0{a: #1{}, b: #2{a: &1{}}}', $depth2->export($value, 2));
        $this->assertSame('#0{a: #1{}, b: #2{a: &1{}}}', $this->subject->export($value));
        $this->assertSame('#1{}', $depth0->export($object, 0));
    }

    public function testExportRecursiveObject()
    {
        $value = new TestClass();
        $value->inner = $value;

        $this->assertSame('TestClass#0{inner: &0{}}', $this->subject->export($value));
    }

    public function testExportRecursiveArray()
    {
        $value = array();
        $value['inner'] = &$value;

        $this->assertSame('#0["inner": &0[]]', $this->subject->export($value));
    }

    public function testExportObjectPersistentIds()
    {
        $objectA = (object) array();
        $objectB = (object) array();

        $this->assertSame('#0{}', $this->subject->export($objectA));
        $this->assertSame('#1{}', $this->subject->export($objectB));
        $this->assertSame('#0{}', $this->subject->export($objectA));
    }

    public function testExportInaccessibleProperties()
    {
        $value = new TestDerivedClass();
        $actual = $this->subject->export($value);

        $this->assertContains('derivedPublic: "<derived-public>"', $actual);
        $this->assertContains('derivedProtected: "<derived-protected>"', $actual);
        $this->assertContains('derivedPrivate: "<derived-private>"', $actual);
        $this->assertContains('basePublic: "<base-public>"', $actual);
        $this->assertContains('baseProtected: "<base-protected>"', $actual);
        $this->assertContains('basePrivate: "<derived-base-private>"', $actual);
        $this->assertContains('TestBaseClass.basePrivate: "<base-private>"', $actual);
    }

    public function testExportClosure()
    {
        $line = __LINE__ + 1;
        $value = function () {
        };

        $this->assertSame(
            'Closure#0{}[' . basename(__FILE__) . ':' . $line . ']',
            $this->subject->export($value)
        );
    }

    public function testExportExceptions()
    {
        $exceptionA = new RuntimeException();
        $exceptionB = new RuntimeException('message');
        $exceptionC = new RuntimeException('message', 111);
        $exceptionD = new RuntimeException('message', 111, $exceptionA);
        $exceptionE = new RuntimeException('message', 111, $exceptionA);
        $exceptionE->arbitrary = 'yolo';

        $this->assertSame('RuntimeException#0{}', $this->subject->export($exceptionA));
        $this->assertSame('RuntimeException#1{message: "message"}', $this->subject->export($exceptionB));
        $this->assertSame('RuntimeException#2{message: "message", code: 111}', $this->subject->export($exceptionC));
        $this->assertSame(
            'RuntimeException#3{message: "message", code: 111, previous: RuntimeException#0{}}',
            $this->subject->export($exceptionD)
        );
        $this->assertSame(
            'RuntimeException#4{message: "message", code: 111, previous: RuntimeException#0{}, arbitrary: "yolo"}',
            $this->subject->export($exceptionE)
        );
    }

    public function testExportGenerators()
    {
        if (!class_exists('Generator')) {
            $this->markTestSkipped('Requires generators.');
        }

        $generator = call_user_func(
            function () {
                return;
                yield;
            }
        );

        $this->assertSame('Generator#0{}', $this->subject->export($generator));
    }
}
<?php
namespace Psalm\Tests;

use function define;
use function defined;
use function dirname;
use function getcwd;
use function implode;
use function explode;
use const DIRECTORY_SEPARATOR;
use Psalm\Config;
use Psalm\Context;
use Psalm\Internal\Analyzer\FileAnalyzer;
use Psalm\Internal\IncludeCollector;
use Psalm\Internal\RuntimeCaches;
use Psalm\Tests\Internal\Provider;

class StubTest extends TestCase
{
    /** @var TestConfig */
    protected static $config;

    public static function setUpBeforeClass() : void
    {
        self::$config = new TestConfig();

        if (!defined('PSALM_VERSION')) {
            define('PSALM_VERSION', '2.0.0');
        }

        if (!defined('PHP_PARSER_VERSION')) {
            define('PHP_PARSER_VERSION', '4.0.0');
        }
    }

    public function setUp() : void
    {
        RuntimeCaches::clearAll();
        $this->file_provider = new Provider\FakeFileProvider();
    }

    private function getProjectAnalyzerWithConfig(Config $config): \Psalm\Internal\Analyzer\ProjectAnalyzer
    {
        $project_analyzer = new \Psalm\Internal\Analyzer\ProjectAnalyzer(
            $config,
            new \Psalm\Internal\Provider\Providers(
                $this->file_provider,
                new Provider\FakeParserCacheProvider()
            )
        );
        $project_analyzer->setPhpVersion('7.3');

        $config->setIncludeCollector(new IncludeCollector());
        $config->visitComposerAutoloadFiles($project_analyzer, null);

        return $project_analyzer;
    }

    public function testNonexistentStubFile(): void
    {
        $this->expectException(\Psalm\Exception\ConfigException::class);
        $this->expectExceptionMessage('Cannot resolve stubfile path');
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            Config::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="stubs/invalidfile.php" />
                    </stubs>
                </psalm>'
            )
        );
    }

    public function testStubFileClass(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/systemclass.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace A\B\C;

                $a = new \SystemClass();
                $b = $a->foo(5, "hello");
                $c = \SystemClass::bar(5, "hello");
                echo \SystemClass::HELLO;'
        );

        $this->analyzeFile($file_path, new Context());
    }

    /**
     * @psalm-pure
     */
    private function getOperatingSystemStyledPath(string $file): string
    {
        return implode(DIRECTORY_SEPARATOR, explode('/', $file));
    }

    public function testLoadStubFileWithRelativePath(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <stubs>
                        <file name="./tests/../tests/fixtures/stubs/systemclass.php" />
                    </stubs>
                </psalm>'
            )
        );

        $path = $this->getOperatingSystemStyledPath('tests/fixtures/stubs/systemclass.php');
        $stub_files = $this->project_analyzer->getConfig()->getStubFiles();
        $this->assertStringContainsString($path, \reset($stub_files));
    }

    public function testLoadStubFileWithAbsolutePath(): void
    {
        $runDir = dirname(__DIR__);
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                $runDir,
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <stubs>
                        <file name="' . $runDir . '/tests/fixtures/stubs/systemclass.php" />
                    </stubs>
                </psalm>'
            )
        );

        $path = $this->getOperatingSystemStyledPath('tests/fixtures/stubs/systemclass.php');
        $stub_files = $this->project_analyzer->getConfig()->getStubFiles();
        $this->assertStringContainsString($path, \reset($stub_files));
    }

    public function testStubFileConstant(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/systemclass.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace A\B\C;

                $d = ROOT_CONST_CONSTANT;
                $e = \ROOT_CONST_CONSTANT;
                $f = ROOT_DEFINE_CONSTANT;
                $g = \ROOT_DEFINE_CONSTANT;'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testPhpStormMetaParsingFile(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/phpstorm.meta.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace Ns {
                    class MyClass {
                        /**
                         * @return mixed
                         * @psalm-suppress InvalidReturnType
                         */
                        public function create(string $s) {}

                        /**
                         * @param mixed $s
                         * @return mixed
                         * @psalm-suppress InvalidReturnType
                         */
                        public function foo($s) {}

                        /**
                         * @return mixed
                         * @psalm-suppress InvalidReturnType
                         */
                        public function bar(array $a) {}
                    }
                }
                namespace {
                    /**
                     * @return mixed
                     * @psalm-suppress InvalidReturnType
                     */
                    function create(string $s) {}

                    /**
                     * @param mixed $s
                     * @return mixed
                     * @psalm-suppress InvalidReturnType
                     */
                    function foo($s) {}

                    /**
                     * @return mixed
                     * @psalm-suppress InvalidReturnType
                     */
                    function bar(array $a) {}

                    $a1 = (new \Ns\MyClass)->creAte("object");
                    $a2 = (new \Ns\MyClass)->creaTe("exception");

                    $b1 = \Create("object");
                    $b2 = \cReate("exception");

                    $e2 = \creAte(\LogicException::class);

                    $c1 = (new \Ns\MyClass)->foo(5);
                    $c2 = (new \Ns\MyClass)->bar(["hello"]);

                    $d1 = \foO(5);
                    $d2 = \baR(["hello"]);
                }'
        );

        $context = new Context();
        $this->analyzeFile($file_path, $context);
    }

    public function testNamespacedStubClass(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/namespaced_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                $a = new Foo\SystemClass();
                echo Foo\SystemClass::HELLO;

                $b = $a->foo(5, "hello");
                $c = Foo\SystemClass::bar(5, "hello");

                echo Foo\BAR;'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubRegularFunction(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/custom_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                echo barBar("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubVariadicFunction(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/custom_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                variadic("bat", "bam");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubVariadicFunctionWrongArgType(): void
    {
        $this->expectExceptionMessage('InvalidScalarArgument');
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/custom_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                variadic("bat", 5);'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testUserVariadicWithFalseVariadic(): void
    {
        $this->expectExceptionMessage('TooManyArguments');
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                /**
                 * @param string ...$bar
                 */
                function variadic() : void {}
                variadic("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    /**
     * @runInSeparateProcess
     */
    public function testPolyfilledFunction(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                    autoloader="tests/fixtures/stubs/polyfill.php"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                $a = random_bytes(16);
                $b = new_random_bytes(16);'
        );

        $this->analyzeFile($file_path, new Context());
    }

    /**
     * @runInSeparateProcess
     */
    public function testConditionalConstantDefined(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                    autoloader="tests/fixtures/stubs/conditional_constant_define_inferred.php"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                echo CODE_DIR;'
        );

        $this->analyzeFile($file_path, new Context());
    }

    /**
     * @runInSeparateProcess
     */
    public function testClassAlias(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                    autoloader="tests/fixtures/stubs/class_alias.php"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace ClassAliasStubTest;

                function foo(A $a) : void {}

                foo(new B());
                foo(new C());

                function bar(B $b) : void {}

                bar(new A());

                $a = new B();

                echo $a->foo;

                echo $a->bar("hello");

                function f(): A {
                    return new A;
                }

                function getAliased(): B {
                    return f();
                }

                $d = new D();

                D::bat();
                $d::bat();

                class E implements IAlias {}'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFunctionWithFunctionExists(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/custom_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                function_exists("fooBar");
                echo barBar("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testNamespacedStubFunctionWithFunctionExists(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/custom_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace A;
                function_exists("fooBar");
                echo barBar("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testNoStubFunction(): void
    {
        $this->expectExceptionMessage('UndefinedFunction - /src/somefile.php:2:22 - Function barBar does not exist');
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                echo barBar("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testNamespacedStubFunction(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/namespaced_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                echo Foo\barBar("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testConditionalNamespacedStubFunction(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/conditional_namespaced_functions.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                echo Foo\barBar("hello");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testConditionallyExtendingInterface(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/conditional_interface.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                class C implements I1, I2, I3, I4 {}

                function foo(I5 $d) : void {
                    $d->getMessage();
                }

                function bar(I6 $d) : void {
                    $d->getMessage();
                }

                function bat(I7 $d) : void {
                    $d->getMessage();
                }

                function baz(I8 $d) : void {
                    $d->getMessage();
                }'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFileWithExistingClassDefinition(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/DomainException.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                $a = new DomainException(5);'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFileWithPartialClassDefinitionWithMoreMethods(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/partial_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace Foo;

                class PartiallyStubbedClass  {
                    /**
                     * @param string $a
                     * @return object
                     */
                    public function foo(string $a) {
                        return new self;
                    }

                    public function bar(int $i) : void {}
                }

                class A {}

                (new PartiallyStubbedClass())->foo(A::class);
                (new PartiallyStubbedClass())->bar(5);'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testExtendOnlyStubbedClass(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/partial_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace Foo;

                class A extends PartiallyStubbedClass {}

                (new A)->foo(A::class);'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFileWithExtendedStubbedClass(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/partial_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace Foo;

                class Bar extends PartiallyStubbedClass  {}

                new Bar();'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFileWithPartialClassDefinitionWithCoercion(): void
    {
        $this->expectExceptionMessage('TypeCoercion');
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/partial_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace Foo;

                class PartiallyStubbedClass  {
                    /**
                     * @param string $a
                     * @return object
                     */
                    public function foo(string $a) {
                        return new self;
                    }
                }

                (new PartiallyStubbedClass())->foo("dasda");'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFileWithPartialClassDefinitionGeneralReturnType(): void
    {
        $this->expectExceptionMessage('InvalidReturnStatement');
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/partial_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                namespace Foo;

                class PartiallyStubbedClass  {
                    /**
                     * @param string $a
                     * @return object
                     */
                    public function foo(string $a) {
                        return new \stdClass;
                    }
                }'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testStubFileWithTemplatedClassDefinitionAndMagicMethodOverride(): void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    errorLevel="1"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>

                    <stubs>
                        <file name="tests/fixtures/stubs/templated_class.php" />
                    </stubs>
                </psalm>'
            )
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                class A {
                    /**
                     * @param int $id
                     * @param ?int $lockMode
                     * @param ?int $lockVersion
                     * @return mixed
                     */
                    public function find($id, $lockMode = null, $lockVersion = null) {}
                }

                class B extends A {}

                class Obj {}

                /**
                 * @method ?Obj find(int $id, $lockMode = null, $lockVersion = null)
                 */
                class C extends B {}'
        );

        $this->analyzeFile($file_path, new Context());
    }

    public function testInheritedMethodUsedInStub() : void
    {
        $this->project_analyzer = $this->getProjectAnalyzerWithConfig(
            TestConfig::loadFromXML(
                dirname(__DIR__),
                '<?xml version="1.0"?>
                <psalm
                    findUnusedCode="true"
                >
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>'
            )
        );

        $this->project_analyzer->getCodebase()->reportUnusedCode();

        $vendor_file_path = getcwd() . '/vendor/vendor_class.php';

        $this->addFile(
            $vendor_file_path,
            '<?php
                namespace SomeVendor;

                class VendorClass {
                    abstract public function foo() : void;

                    public static function vendorFunction(VendorClass $v) : void {
                        $v->foo();
                    }
                }'
        );

        $file_path = getcwd() . '/src/somefile.php';

        $this->addFile(
            $file_path,
            '<?php
                class MyClass extends \SomeVendor\VendorClass {
                    public function foo() : void {}
                }

                \SomeVendor\VendorClass::vendorFunction(new MyClass);'
        );

        $this->analyzeFile($file_path, new Context(), false);

        $this->project_analyzer->consolidateAnalyzedData();
    }
}

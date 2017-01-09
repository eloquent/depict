<?php

namespace Eloquent\Depict;

use Athletic\AthleticEvent;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class InlineExporterEvent extends AthleticEvent
{
    protected function setUp()
    {
        $this->data = require __DIR__ . '/data/typical.php';

        $this->depict = InlineExporter::create();

        $this->symfony = new CliDumper();
        $this->symfonyCloner = new VarCloner();
    }

    /**
     * @iterations 100
     */
    public function depict()
    {
        $this->depict->export($this->data);
    }

    /**
     * @iterations 100
     */
    public function symfony()
    {
        $this->symfony->dump($this->symfonyCloner->cloneVar($this->data), true);
    }
}

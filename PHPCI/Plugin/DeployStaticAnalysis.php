<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI\Builder;
use PHPCI\Model\Build;
use PHPCI\Helper\Lang;

/**
 * Copy Build Plugin - Copies the entire build to another directory.
 * @author       Dan Cryer <dan@block8.co.uk>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class DeployStaticAnalysis implements \PHPCI\Plugin
{
    protected $phpci;
    protected $build;
    protected $options;

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci     = $phpci;
        $this->build     = $build;

        $this->options = $options;
    }


    /**
     * Moves the static analysis log files into doc root before the build is destroyed
     *
     * @return bool
     * @throws \Exception
     */
    public function execute()
    {
        $srcDir = $this->build->getBuildPath() . '/build/logs/report';
        $destDir = APPLICATION_PATH . 'public/reports';

        $cmd = 'rm -Rf "%s*"';
        $success = $this->phpci->executeCommand($cmd, $destDir);

        if (!$success) {
            throw new \Exception(Lang::get('failed_to_wipe', $destDir));
        }

        // Attempt to move the reports directory
        return rename($srcDir, $destDir);
    }
}

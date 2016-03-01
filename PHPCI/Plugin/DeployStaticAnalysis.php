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

    protected $reportUrl;


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

        $this->reportUrl = PHPCI_URL . "/reports/";
    }


    /**
     *
     * Moves the static analysis log files into doc root before the build is destroyed
     *
     * @return bool
     * @throws \Exception
     */
    public function execute()
    {
        $srcDir = $this->build->getBuildPath() . '/build/logs/report';
        $destDir = APPLICATION_PATH . 'public/reports';


        $result = exec(
            sprintf(IS_WIN ? 'rmdir /S /Q "%s"' : 'rm -Rf "%s"', $destDir),
            $output,
            $exitStatus
        );

        // Non-zero status is an error scenario
        if ($exitStatus !== 0) {
            // Wipe failed - exception here
            throw new \Exception(Lang::get('failed_to_wipe', $destDir));
        }

        $moveResult = false;

        if (!is_dir($destDir)) {
            $moveResult = rename($srcDir, $destDir);
        }

        switch($moveResult) {
            case true:
                $logMessage = "Coverage reports deployed at ";
                $logMessage .= "{$this->reportUrl}";
                $this->phpci->logSuccess($logMessage);
                break;
            default:
                $logMessage = "Oopsies";
                $this->phpci->logFailure($logMessage);
                break;
        }

        return true;
    }
}

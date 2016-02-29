<?php
/**
 * PHPCI - Continuous Integration for PHP
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI\Builder;
use PHPCI\Model\Build;
use Nack\Monolog\Handler\GitterImHandler;
use Monolog\Logger;

/**
 * Slack Plugin
 * @author       Stephen Ball <phpci@stephen.rebelinblue.com>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class GitterNotify implements \PHPCI\Plugin
{
    private $phpci;
    private $build;
    private $token;
    private $room;
    private $message;
    private $show_status = true;
    private $logger;
    private $buildStatus;
    private $statusMessage = "Build passed";

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     * @throws \Exception
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci = $phpci;
        $this->build = $build;

        if (is_array($options)) {

            if (isset($options['message'])) {
                $this->message = $options['message'];
            } else {



                $this->message = "%PROJECT_TITLE% Build #%BUILD% Result {{RESULT_STATUS}} \n";
                $this->message .= "Push from %COMMIT_EMAIL% \n";
                $this->message .= "on branch %BRANCH% \n";
                $this->message .= "\n\n";
                $this->message .= $this->collectPhpUnitSummary();
            }

            if (!isset($options['token'])) {
                throw new \Exception('Token is a required parameter');
            }

            $this->token = $options['token'];

            if (!isset($options['room'])) {
                throw new \Exception('Room must be specified with Gitter plugin');
            }

            $this->room = $options['room'];
            $this->show_status = isset($options['show_status']) ? (bool)$options['show_status'] : true;

        } else {
            throw new \Exception('Gitter notify plugin requires room and token options to function');
        }


        $logger = new Logger('phpci.gitter.buildLogger');
        $logger->pushHandler(
            new GitterImHandler($this->token, $this->room, Logger::INFO)
        );

        $this->logger = $logger;

    }

    private function collectPhpUnitSummary()
    {
        $report = file_get_contents($this->build->getBuildPath() . '/build/reports/coverage.txt');


        $pattern = "#(?<date>[-0-9]+)\s+(?<time>[:0-9]+)\s+Summary:\s+";
        $pattern .= "(?<classname>[^:]+):\s+(?<classpercent>[^\s]+)\s+(?<classratio>[^\s]+)\s+";
        $pattern .= "(?<methodname>[^:]+):\s+(?<methodpercent>[^\s]+)\s+(?<methodratio>[^\s]+)\s+";
        $pattern .= "(?<linesname>[^:]+):\s+(?<linespercent>[^\s]+)\s+(?<linesratio>[^\s]+)#im";

        preg_match($pattern, $report, $matches);

        $returnVal = "
|         | Classes                   | Methods                     | Lines                      |
|---------|---------------------------|-----------------------------|----------------------------|
| Percent |{$matches['classpercent']} | {$matches['methodpercent']} | {$matches['linespercent']} |
| Ratio   |{$matches['classratio']}   | {$matches['methodratio']}   | {$matches['linesratio']}   |";


        $this->buildStatus = ((bool) ((float)$matches['classpercent'] >= 80)) ? Build::STATUS_SUCCESS : Build::STATUS_FAILED;
        $this->build->setStatus($this->buildStatus);

        $buildLink = "http://ec2-52-48-108-124.eu-west-1.compute.amazonaws.com/build/view/" . $this->build->getId();

        switch($this->buildStatus) {
            case Build::STATUS_FAILED:
                $this->statusMessage = "Build failed: Unit test coverage too low";
                break;
            case Build::STATUS_SUCCESS:
                $this->statusMessage = "Build ok - Coverage above 80% threshold";
                break;
        }

        $returnVal .= "\n| Status | [{$this->statusMessage}]($buildLink) |\n";

        return $returnVal;
    }

    /**
     * Run the Gitter plugin.
     *
     * @return bool
     */
    public function execute()
    {

        if ($this->hasRun()) {
            return true; // nothing to do
        }

        $body = $this->phpci->interpolate($this->message);

        $successfulBuild = $this->build->isSuccessful();
        $statusMessage = $successfulBuild ? "SUCCESS :sunny: :beer:" : "FAIL :umbrella: :zap:";

        $body = str_replace('{{RESULT_STATUS}}', $statusMessage, $body);


        $body = ($successfulBuild ? ":white_check_mark: " : ":red_circle: ") . $body;

        $body = json_encode( ["text" => $body]);

        $url = "https://api.gitter.im";
        $path = "/v1/rooms/{$this->room}/chatMessages";

        $headers = [
            "Authorization: Bearer {$this->token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ];

        $this->curlSend($url, $path, $body, $headers);

        $this->markAsRun();

        $this->phpci->log($this->statusMessage);

        return (bool) $this->buildStatus == Build::STATUS_SUCCESS;
    }

    private function markAsRun()
    {
        $fileName = $this->build->getBuildPath();
        return touch($fileName);
    }

    private function hasRun()
    {
        return file_exists($this->build->getBuildPath() . '/gitter.notified');
    }

    private function curlSend($url, $path, $payload, $headers, $verb = "POST") {


        $ch = curl_init($url . $path);


        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        switch ($verb) {
            case "PUT":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            default:
                curl_setopt($ch, CURLOPT_POST, true);
                break;
        }


        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }
}

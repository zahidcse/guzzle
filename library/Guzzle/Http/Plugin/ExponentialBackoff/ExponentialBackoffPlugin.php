<?php

namespace Guzzle\Http\Plugin\ExponentialBackoff;

use \Closure;
use Guzzle\Common\Subject\Subject;
use Guzzle\Common\Subject\SubjectMediator;
use Guzzle\Common\Subject\Observer;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Plugin\AbstractPlugin;

/**
 * Plugin class that will retry failed HTTP requests using truncated exponential
 * backoff.
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class ExponentialBackoffPlugin extends AbstractPlugin
{
    /**
     * @var array Array of response codes that must be retried
     */
    protected $failureCodes;

    /**
     * @var int Maximum number of times to retry a request
     */
    protected $maxRetries;

    /**
     * @var array Request state information
     */
    protected $state = array();

    /**
     * @var Closure
     */
    protected $delayClosure;

    /**
     * Construct a new exponential backoff plugin
     *
     * @param int $maxRetries (optional) The maximum number of time to retry a request
     * @param array $failureCodes (optional) Pass a custom list of failure codes.
     * @param Closure|array $delayClosure (optional) Method used to calculate the
     *      delay between requests.  The method must accept an integer containing
     *      the current number of retries and return an integer representing how
     *      many seconds to delay
     */
    public function __construct($maxRetries = 3, array $failureCodes = null, $delayClosure = null)
    {
        $this->setMaxRetries($maxRetries);
        $this->failureCodes = $failureCodes ?: array(500, 503);
        $this->delayClosure = $delayClosure ?: array($this, 'calculateWait');
    }

    /**
     * {@inheritdoc}
     */
    protected function handleAttach(RequestInterface $request)
    {
        $this->state[spl_object_hash($request)] = 0;
    }

    /**
     * Set the maximum number of retries the plugin should use before failing
     * the request
     *
     * @param integer $maxRetries The maximum number of retries.
     *
     * @return ExponentialBackoffPlugin
     */
    public function setMaxRetries($maxRetries)
    {
        $this->maxRetries = max(0, (int)$maxRetries);

        return  $this;
    }

    /**
     * Get the maximum number of retries the plugin will attempt
     *
     * @return integer
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * Get the HTTP response codes that should be retried using truncated
     * exponential backoff
     *
     * @return array
     */
    public function getFailureCodes()
    {
        return $this->failureCodes;
    }

    /**
     * Set the HTTP response codes that should be retried using truncated
     * exponential backoff
     *
     * @param array $codes Array of HTTP response codes
     *
     * @return ExponentialBackoffPlugin
     */
    public function setFailureCodes(array $codes)
    {
        $this->failureCodes = $codes;

        return $this;
    }

    /**
     * Determine how long to wait using truncated exponential backoff
     *
     * @param int $retries Number of retries so far
     *
     * @return int
     */
    public function calculateWait($retries)
    {
        return (int)(pow(2, $retries));
    }

    /**
     * {@inheritdoc}
     *
     * @param RequestInterface $command Request to process
     */
    public function process($command)
    {
        $key = spl_object_hash($command);

        // @codeCoverageIgnoreStart
        // Make sure it's the right object and has been attached to the plugin
        if (!$command instanceof RequestInterface || !array_key_exists($key, $this->state)) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        // Make sure that the request needs to be retried
        if ($command->getState() == RequestInterface::STATE_COMPLETE && in_array($command->getResponse()->getStatusCode(), $this->failureCodes)) {

            // If this request has been retried too many times, then throw an exception
            if (++$this->state[$key] <= $this->maxRetries) {
                // Calculate how long to wait until the request should be retried
                $delay = (int) call_user_func($this->delayClosure, $this->state[$key]);

                // Send the request again
                $command->setState(RequestInterface::STATE_NEW);

                // Pooled requests need to be sent via curl multi
                if ($command->getParams()->get('pool')) {
                    $command->getParams()->get('pool')
                        ->getSubjectMediator()
                        ->attach(new ExponentialBackoffObserver($command, $delay));
                } else {
                    // Wait for a delay then retry the request
                    sleep($delay);
                    $command->send();
                }
            }
        }
    }
}
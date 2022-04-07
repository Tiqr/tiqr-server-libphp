<?php 
/**
 * This file is part of the tiqr project.
 * 
 * The tiqr project aims to provide an open implementation for 
 * authentication using mobile devices. It was initiated by 
 * SURFnet and developed by Egeniq.
 *
 * More information: http://www.tiqr.org
 *
 * @author Ivo Jansch <ivo@egeniq.com>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2011 SURFnet BV
 */

use Psr\Log\LoggerInterface;


/**
 * File based implementation to store session state data. 
 * Note that it is more secure to use a memory based storage such as memcached.
 * This implementation is mostly for demo, test or experimental setups that 
 * do not have access to a memcache instance.
 * 
 * This StateStorage implementation has no options, files are always stored
 * in /tmp and prefixed with tiqr_state_*
 * 
 * @author ivo
 *
 */
class Tiqr_StateStorage_File implements Tiqr_StateStorage_StateStorageInterface
{
    private $logger;

    private $path;

    public function __construct(string $path, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->path = $path;
    }

    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::setValue()
     */
    public function setValue($key, $value, $expire=0)
    {   
        $envelope = array("expire"=>$expire,
                          "createdAt"=>time(),
                          "value"=>$value);
        $filename = $this->getFilenameByKey($key);
        
        if (!file_put_contents($filename, serialize($envelope))) {
            $this->logger->error('Unable to write the value to state storage');
        }
        
        return $key;
    }

    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::unsetValue()
     */
    public function unsetValue($key)
    {
        $filename = $this->getFilenameByKey($key);
        if (file_exists($filename)) {
            unlink($filename);
        } else {
            $this->logger->error('Unable to unlink the value from state storage, key not found on filesystem');
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::getValue()
     */
    public function getValue($key)
    {
        $filename = $this->getFilenameByKey($key);
        if (file_exists($filename)) {
            $envelope = unserialize(file_get_contents($filename), ['allowed_classes' => false]);
            // This data is time-limited. If it's too old we discard it.
            if (($envelope["expire"] != 0) && time() - $envelope["createdAt"] > $envelope["expire"]) {
                $this->unsetValue($key);
                $this->logger->notice('Unable to retrieve the state storage value, it is expired');
                return null;
            }
            return $envelope["value"];
        }
        $this->logger->notice('Unable to retrieve the state storage value, file not found');
        return NULL;
    }

    private function getPath(): string
    {
        if (substr($this->path, -1)!=="/") {
            return $this->path . "/";
        }
        return $this->path;
    }

    /**
     * Determine the name of a temporary file to hold the contents of $key
     */
    private function getFilenameByKey(string $key): string
    {
        return sprintf(
            "%stiqr_state_%s",
            $this->getPath(),
            strtr(base64_encode($key), '+/', '-_')
        );
    }

    public function init()
    {
        # Nothing to do here
    }
}

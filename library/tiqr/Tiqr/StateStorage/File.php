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
     * @see Tiqr_StateStorage_StateStorageInterface::setValue()
     */
    public function setValue(string $key, $value, int $expire=0): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }

        $envelope = array("expire"=>$expire,
                          "createdAt"=>time(),
                          "value"=>$value);
        $filename = $this->getFilenameByKey($key);
        
        if (!file_put_contents($filename, serialize($envelope))) {
            throw new ReadWriteException(sprintf('Unable to store "%s" state to the filesystem', $key));
        }
    }

    /**
     * @see Tiqr_StateStorage_StateStorageInterface::unsetValue()
     */
    public function unsetValue(string $key): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }

        $filename = $this->getFilenameByKey($key);
        if (file_exists($filename) && !unlink($filename)) {
            throw new ReadWriteException(
                sprintf(
                    'Unable to unlink the "%s" value from state storage on filesystem',
                    $key
                )
            );
        }
    }
    
    /**
     * @see Tiqr_StateStorage_StateStorageInterface::getValue()
     */
    public function getValue(string $key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }

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

    public function init(): void
    {
        # Nothing to do here
    }
}

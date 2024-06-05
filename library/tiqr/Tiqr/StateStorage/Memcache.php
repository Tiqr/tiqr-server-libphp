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


/**
 * A StateStorage implementation using memcache to store state information.
 * 
 * When passing $options to the StateStorage::getStorage method, you can use
 * the following settings:
 * - "servers": An array of servers with "host" and "port" keys. If "port" is 
 *              ommited the default port is used.
 *              This option is optional; if servers is not specified, we will
 *              assume a memcache on localhost on the default memcache port.
 * - "prefix": If the memcache server is shared with other applications, a prefix
 *             can be used so that there are no key collisions with other 
 *             applications.
 * 
 * @author ivo
 */
class Tiqr_StateStorage_Memcache extends Tiqr_StateStorage_Abstract
{    
    /**
     * The memcache instance.
     * @var Memcache
     */
    protected $_memcache = NULL;

    /**
     * The flavor of memcache PHP extension we are using.
     * @var string
     */
    private static $extension = '';

    /**
     * The default configuration
     */
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT =  11211;
    
    /**
     * Get the prefix to use for all keys in memcache.
     * @return String the prefix
     */
    protected function _getKeyPrefix()
    {
        if (isset($this->_options["prefix"])) {
            return $this->_options["prefix"];
        }

        $this->logger->info('No key prefix was configured, using "" as default memcache prefix.');
        return "";
    }
    
    /**
     * Initialize the statestorage by setting up the memcache instance.
     * It's not necessary to call this function, the Tiqr_StateStorage factory
     * will take care of that.
     *
     * @see Tiqr_StateStorage_StateStorageInterface::init()
     */
    public function init(): void
    {
        parent::init();

        $class = class_exists('\Memcache') ? '\Memcache' : (class_exists('\Memcached') ? '\Memcached' : false);
        if (!$class) {
            throw new RuntimeException('Memcache or Memcached class not found');
        }
        self::$extension = strtolower($class);
        
        $this->_memcache = new $class();

        if (!isset($this->_options["servers"])) {
            $this->logger->info('No memcache server was configured for StateStorage, using preconfigured defaults.');
            $this->_memcache->addServer(self::DEFAULT_HOST, self::DEFAULT_PORT);
        } else {
            foreach ($this->_options['servers'] as $server) {
                if (!array_key_exists('port', $server)) {
                    $server['port'] = self::DEFAULT_PORT;
                } 
                if (!array_key_exists('host', $server)) {
                    $server['host'] = self::DEFAULT_HOST;
                }  
             
                $this->_memcache->addServer($server['host'], $server['port']);
            }
        }          
    }
    
    /**
     * @see Tiqr_StateStorage_StateStorageInterface::setValue()
     */
    public function setValue(string $key, $value, int $expire=0): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }

        $key = $this->_getKeyPrefix().$key;

        if (self::$extension === '\memcached') {
            $result = $this->_memcache->set($key, $value, $expire);
        } else {
            $result = $this->_memcache->set($key, $value, 0, $expire);
        }
        if (!$result) {
            throw new ReadWriteException(sprintf('Unable to store key "%s" to Memcache StateStorage', $key));
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

        $key = $this->_getKeyPrefix().$key;
        $result = $this->_memcache->delete($key);
        if (!$result) {
            throw new ReadWriteException(
                sprintf(
                    'Unable to delete key "%s" from state storage, key not found in Memcache StateStorage',
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

        $key = $this->_getKeyPrefix().$key;

        $result = $this->_memcache->get($key);
        if ($result === false) {
            // Memcache interface does not provide error information, either the key does not exist or
            // there was an error communicating with the memcache
            $this->logger->info( sprintf('Unable to get key "%s" from memcache StateStorage', $key) );
            return null;
        }
        return $result;
    }

    /**
     * @see Tiqr_HealthCheck_Interface::healthCheck()
     */
    public function healthCheck(string &$statusMessage = ''): bool
    {
        try {
            // Generate a random key and use it to store a value in the memcache
            $key = bin2hex(random_bytes(16));
            $this->setValue($key, 'healthcheck', 10);
        } catch (Exception $e) {
            $statusMessage = 'Unable to store key in memcache: ' . $e->getMessage();
            return false;
        }

        return true;
    }

}

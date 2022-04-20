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
     */
    public function init() 
    {
        parent::init();

        $class = class_exists('\Memcache') ? '\Memcache' : (class_exists('\Memcached') ? '\Memcached' : false);
        self::$extension = strtolower($class);
        
        $this->_memcache = new $class();

        if (!isset($this->_options["servers"])) {
            $this->logger->info('No memcache server was configured for state storage, using preconfigured defaults.');
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
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::setValue()
     */
    public function setValue($key, $value, $expire=0)
    {  
        $key = $this->_getKeyPrefix().$key;

        if (self::$extension === '\memcached') {
            $result = $this->_memcache->set($key, $value, $expire);
        } else {
            $result = $this->_memcache->set($key, $value, 0, $expire);
        }
        if (!$result) {
            throw new ReadWriteException(sprintf('Unable to store "%s" state to Memcache', $key));
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::unsetValue()
     */
    public function unsetValue($key)
    {
        $key = $this->_getKeyPrefix().$key;
        $result = $this->_memcache->delete($key);
        if (!$result) {
            throw new ReadWriteException(
                sprintf(
                    'Unable to unlink the "%s" value from state storage, key not found in Memcache',
                    $key
                )
            );
        }
        return $result;
    }
    
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::getValue()
     */
    public function getValue($key)
    {
        $key = $this->_getKeyPrefix().$key;

        $result = $this->_memcache->get($key);
        if ($result === false) {
            $this->logger->error('Unable to retrieve the state storage value from memcache, file not found');
            return null;
        }
        return $result;
    }
        
}

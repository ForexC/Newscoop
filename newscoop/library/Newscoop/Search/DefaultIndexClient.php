<?php
/**
 * @package Newscoop
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Search;

use Symfony\Component\DependencyInjection\Container;
use Newscoop\Search\IndexClientInterface;
use Newscoop\Search\ServiceInterface;
use Newscoop\Search\DocumentInterface;
use Newscoop\Search\QueryInterface;

/**
 * Solr Index
 */
class DefaultIndexClient implements IndexClientInterface
{
    /**
     *
     */
    const TEXT_FILE = 'temp/';

    /**
     * Indexable item
     *
     * @var Newscoop\Search\DocumentInterface
     */
    private $item;

    /**
     * Newscoop service interface
     *
     * @var Newscoop\Search\ServiceInterface
     */
    private $service;

    /**
     * Symfony container
     *
     * @var Symfony\Component\DependencyInjection\Container
     */
    private $container;

    /**
     * Contains data to be added
     *
     * @var array
     */
    private $add = array();

    /**
     * Contains data to be updated
     *
     * @var array
     */
    private $update = array();

    /**
     * Contains data to be deleted
     *
     * @var array
     */
    private $delete = array();

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Adds data to inderxer
     *
     * @param array $document Indexable array, must contain key 'id'
     *
     * @return boolean
     */
    public function add(array $document)
    {
        $this->add[] = $document;

        return true;
    }

    /**
     * Updates element in indexer (if supported)
     *
     * @param  array  $document Re-indexable data, must contain key 'id'
     *
     * @return boolean
     */
    public function update(array $document)
    {
        $this->update[] = $document;

        return true;
    }

    /**
     * Delete item from index
     *
     * @param  string $document Id of indexable data
     *
     * @return boolean
     */
    public function delete($document)
    {
        $this->delete[] = $document;

        return true;
    }

    /**
     * Flush all commands
     *
     * @return boolean
     */
    public function flush()
    {
        $debug = $this->container->get('kernel')->isDebug();

        // Only log and write to text files when debugging
        if (!$debug) {
            return true;
        }

        $logger = $this->container->get('logger');

        $commandList = array(
            'add' => $this->add,
            'update' => $this->update,
            'delete' => $this->delete,
        );

        $logger->info(__CLASS__ .': start logging index commands');

        foreach ($commandList as $name => $commands) {
            foreach ($commands AS $command) {
                $logString = (is_array($command)) ? json_encode($command) : $command;
                $logger->info($name.': '.$logString);
            }
        }

        $logger->info(__CLASS__. ': finished logging index commands');

        return true;
    }

    /**
     * Delete all indexed data
     *
     * @return boolean
     */
    public function deleteAll()
    {
        return true;
    }

    /**
     *
     */
    public function find(QueryInterface $query)
    {
        $debug = $this->container->get('kernel')->isDebug();

        if ($debug) {
            $logger->info(''. __CLASS__ .': find command not implemented.');
        }
    }

    /**
     * Set service for
     *
     * @param ServiceInterface $service
     */
    public function setService(ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Set item. This method gives the possibility for the indexing client
     * to access extra data in regards to the default indexable content;
     *
     * @param DocumentInterface $item
     */
    public function setItem(DocumentInterface $item)
    {
        $this->item = $item;
    }
}

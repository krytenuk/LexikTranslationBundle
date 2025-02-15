<?php

namespace Lexik\Bundle\TranslationBundle\Storage;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManager;

/**
 * Doctrine ORM storage class.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class DoctrineORMStorage extends AbstractDoctrineStorage
{
    /**
     * Returns true if translation tables exist.
     *
     * @return boolean
     */
    public function translationsTablesExist()
    {
        /** @var EntityManager $em */
        $em = $this->getManager();
        $connection = $em->getConnection();

        // listDatabases() is not available for SQLite
        if (!$connection->getDriver() instanceof SQLiteDriver) {
            // init a tmp connection without dbname/path/url in case it does not exist yet
            $params = $connection->getParams();
            if (isset($params['master'])) {
                $params = $params['master'];
            }

            unset($params['dbname'], $params['path'], $params['url']);

            try {
                $tmpConnection = DriverManager::getConnection($params);
                $dbExists = in_array($connection->getDatabase(), $tmpConnection->getSchemaManager()->listDatabases());
                $tmpConnection->close();
            } catch (ConnectionException $e) {
                $dbExists = false;
            } catch (\Exception $e) {
                $dbExists = false;
            }

            if (!$dbExists) {
                return false;
            }
        }

        // checks tables exist
        $tables = array(
            $em->getClassMetadata($this->getModelClass('trans_unit'))->getTableName(),
            $em->getClassMetadata($this->getModelClass('translation'))->getTableName(),
        );

        return $connection->getSchemaManager()->tablesExist($tables);
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestUpdatedAt()
    {
        return $this->getTranslationRepository()->getLatestTranslationUpdatedAt();
    }

    /**
     * {@inheritdoc}
     */
    public function getCountTransUnitByDomains()
    {
        $results = $this->getTransUnitRepository()->countByDomains();

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['domain']] = (int) $row['number'];
        }

        return $counts;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountTranslationByLocales($domain)
    {
        $results = $this->getTranslationRepository()->countByLocales($domain);

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['locale']] = (int) $row['number'];
        }

        return $counts;
    }

    /**
     * Returns the TransUnit repository.
     *
     * @return object
     */
    protected function getTranslationRepository()
    {
        return $this->getManager()->getRepository($this->classes['translation']);
    }
}

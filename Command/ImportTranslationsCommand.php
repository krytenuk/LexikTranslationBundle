<?php

namespace Lexik\Bundle\TranslationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

use Lexik\Bundle\TranslationBundle\Entity\Translation;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;

/**
 * Imports translation files content in the database.
 * Only imports files for locales defined in lexik_translation.managed_locales.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class ImportTranslationsCommand extends ContainerAwareCommand
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    protected function configure()
    {
        $this->setName('lexik:translations:import');
        $this->setDescription('Import all translations from flat files (xliff, yml, php) into the database.');

        $this->addOption('cache-clear', 'c', InputOption::VALUE_NONE, 'Remove translations cache files for managed locales.', null);
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $managedLocales = $this->getContainer()->getParameter('lexik_translation.managed_locales');

        $output->writeln('<info>*** Importing application translation files ***</info>');
        $this->importAppTranslationFiles($output, $managedLocales);

        $output->writeln('<info>*** Importing bundles translation files ***</info>');
        $this->importBundlesTranslationFiles($output, $managedLocales);

        if ($input->getOption('cache-clear')) {
            $output->writeln('<info>Removing translations cache files ...</info>');
            $this->removetranslationCache();
        }
    }

    /**
     * Imports application translation files.
     *
     * @param OutputInterface $output
     */
    public function importAppTranslationFiles(OutputInterface $output, array $locales)
    {
        $finder = $this->findTranslationsFiles($this->getApplication()->getKernel()->getRootDir(), $locales);
        $this->importTranslationFiles($finder, $output);
    }

    /**
     * Imports translation files form all bundles.
     *
     * @param OutputInterface $output
     */
    public function importBundlesTranslationFiles(OutputInterface $output, array $locales)
    {
        $bundles = $this->getApplication()->getKernel()->getBundles();

        foreach ($bundles as $bundle) {
            $output->writeln(sprintf('<info># %s :</info>', $bundle->getName()));
            $finder = $this->findTranslationsFiles($bundle->getPath(), $locales);
            $this->importTranslationFiles($finder, $output);
        }
    }

    /**
     * Imports some translations files.
     *
     * @param Finder $finder
     * @param OutputInterface $output
     */
    protected function importTranslationFiles($finder, OutputInterface $output)
    {
        if ($finder instanceof Finder) {
            foreach ($finder as $file)  {
                $output->write(sprintf('<comment>Importing "%s" ... </comment>', $file->getPathname()));
                $number = $this->importFile($file);
                $output->writeln(sprintf('<comment>%d translations</comment>', $number));
            }
        } else {
            $output->writeln('<comment>No file to import for managed locales.</comment>');
        }
    }

    /**
     * Return a Finder object if $path has a Resources/translations folder.
     *
     * @param string $path
     * @return Symfony\Component\Finder\Finder
     */
    protected function findTranslationsFiles($path, array $locales)
    {
        $finder = null;
        $dir = $path.'/Resources/translations';

        if (is_dir($dir)) {
            $finder = new Finder();
            $finder->files()
                ->name(sprintf('/(.*(%s)\.(xliff|yml|php))/', implode('|', $locales)))
                ->in($dir);
        }

        return $finder;
    }

    /**
     * Import a translation file into the database and return the number of translations added.
     *
     * @param Symfony\Component\Finder\SplFileInfo $file
     * @return int
     */
    protected function importFile(\Symfony\Component\Finder\SplFileInfo $file)
    {
        $imported = 0;
        list($domain, $locale, $extention) = explode('.', $file->getFilename());
        $serviceId = sprintf('translation.loader.%s', $extention);

        if ($this->getContainer()->has($serviceId)) {
            $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
            $repository = $entityManager->getRepository($this->getContainer()->getParameter('lexik_translation.trans_unit.class'));
            $transUnitManager = $this->getContainer()->get('lexik_translation.trans_unit.manager');

            $loader = $this->getContainer()->get($serviceId);
            $messageCatalogue = $loader->load($file->getPathname(), $locale, $domain);

            foreach ($messageCatalogue->all() as $domainName => $messages) {
                foreach ($messages as $key => $content) {
                    $transUnit = $repository->findOneBy(array('key' => $key, 'domain' => $domainName));

                    if (!($transUnit instanceof TransUnit)) {
                        $transUnit = $transUnitManager->create($key, $domainName, true);
                    } else {
                        $entityManager->refresh($transUnit);
                    }

                    $translation = $transUnitManager->addTranslation($transUnit, $locale, $content);
                    if ($translation instanceof Translation) {
                        $imported++;
                    }

                    $entityManager->flush();
                }
            }
        }

        return $imported;
    }

    /**
     * Remove translation cache files managed locales.
     *
     */
    public function removetranslationCache()
    {
        $locales = $this->getContainer()->getParameter('lexik_translation.managed_locales');
        $this->getContainer()->get('translator')->removeLocalesCacheFiles($locales);
    }
}
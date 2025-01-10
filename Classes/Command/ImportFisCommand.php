<?php
namespace Univie\UniviePure\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Database\ConnectionPool;

ini_set("default_socket_timeout", 900);


class ImportFisCommand extends Command
{


    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setHelp('This command imports FIS-EPV data into TYPO3')
            ->setDescription('This command imports FIS-EPV data into TYPO3.');
    }


    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln("run...");
        $classificationScheme = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Univie\UniviePure\Utility\ClassificationScheme');

        try{
            $olddate = (int)$classificationScheme->getOrganisationsFromCache("de_DE")[1];
        }catch (Exception $e) {
            $olddate = 0;
        }
        $io->writeln("Timestamp of records: ". date('c', $olddate));
        if (time() > $olddate + 3600 ){
            $io->writeln("New FIS Import: ". date('d.m.Y H:i:s')."\n");

            $orgXMLDe = '<?xml version="1.0"?>
                <organisationalUnitsQuery>
                <size>999999</size>
                <locales>
                    <locale>de_DE</locale>
                </locales>
                <fields>
                    <field>uuid</field>
                    <field>name.text.value</field>
                </fields>
                <orderings>
                    <ordering>name</ordering>
                </orderings>
                <returnUsedContent>true</returnUsedContent>
                </organisationalUnitsQuery>';

            $orgXMLEn = '<?xml version="1.0"?>
                <organisationalUnitsQuery>
                <size>999999</size>
                <locales>
                    <locale>en_GB</locale>
                </locales>
                <fields>
                    <field>uuid</field>
                    <field>name.text.value</field>
                </fields>
                <orderings>
                    <ordering>name</ordering>
                </orderings>
                <returnUsedContent>true</returnUsedContent>
                </organisationalUnitsQuery>';

            $personXML = '<?xml version="1.0"?>
                <personsQuery>
                <size>999999</size>
                <fields>
                  <field>uuid</field>
                  <field>name.*</field>
                </fields>
                <orderings>
                  <ordering>lastName</ordering>
                </orderings>
                <employmentStatus>ACTIVE</employmentStatus></personsQuery>';

            $projectsXMLEn = trim('<?xml version="1.0"?><projectsQuery><size>999999</size><locales><locale>en_GB</locale></locales>
                <fields>
                <field>uuid</field>
                <field>acronym</field>
                <field>title.*</field>
                </fields>
                <orderings>
                <ordering>title</ordering>
                </orderings>
                <workflowSteps>
                <workflowStep>validated</workflowStep>
                </workflowSteps>
                </projectsQuery>');

            $projectsXMLDe = trim('<?xml version="1.0"?><projectsQuery><size>999999</size><locales><locale>de_DE</locale></locales>
                <fields>
                <field>uuid</field>
                <field>acronym</field>
                <field>title.*</field>
                </fields>
                <orderings>
                <ordering>title</ordering>
                </orderings>
                <workflowSteps>
                <workflowStep>validated</workflowStep>
                </workflowSteps>
                </projectsQuery>');
            try{
                $webservice = new WebService;

                $organisations = $webservice->getJson('organisational-units', $orgXMLDe);
                $classificationScheme->storeOrganisationsToCache($organisations,'de_DE');

                $organisations = $webservice->getJson('organisational-units', $orgXMLEn);
                $classificationScheme->storeOrganisationsToCache($organisations,'en_EN');

                $projects = $webservice->getJson('projects', $projectsXMLDe);
                $classificationScheme->storeProjectsToCache($projects,'de_DE');

                $projects = $webservice->getJson('projects', $projectsXMLEn);
                $classificationScheme->storeProjectsToCache($projects,'en_EN');

                $persons = $webservice->getJson('persons', $personXML);
                $classificationScheme->storePersonsToCache($persons);

            }catch (Exception $e) {
                $io->writeln('Exception abgefangen: ',  $e->getMessage(), "\n");
                return(1);
            }

        }else{
            $io->writeln("nothing to do...");
            return(0);

        }
        return(0);
    }
}

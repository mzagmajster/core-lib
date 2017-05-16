<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Command;

use Mautic\LeadBundle\Helper\Progress;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI Command to import data.
 */
class ImportCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mautic:import')
            ->setDescription('Imports data to Mautic')
            ->setDefinition(
                [
                    new InputOption(
                        'batch',
                        'b',
                        InputOption::VALUE_OPTIONAL,
                        'Batch limit for storing to database',
                        25
                    ),
                    new InputOption('dry-run', 'r', InputOption::VALUE_NONE, 'Do a dry run without actually saving anything.'),
                ]
            )
            ->setHelp(
                <<<'EOT'
                The <info>%command.name%</info> command starts to import CSV files when some are submitted.

<info>php %command.full_name%</info>

Specify the batch number.

<info>php %command.full_name% --batch=30</info>

You can also optionally specify a dry run without saving any records:

<info>php %command.full_name% --batch=30 --dry-run</info>
EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
        $translator = $this->getContainer()->get('translator');
        // $translator->setLocale($this->getContainer()->get('mautic.factory')->getParameter('locale'));

        /** @var \Mautic\LeadBundle\Model\ImportModel $model */
        $model = $container->get('mautic.lead.model.import');

        // $batch    = $input->getOption('batch');
        // $dryRun   = $input->getOption('dry-run');
        $progress = new Progress($output);

        $import = $model->processNext($progress);

        // No import waiting in the queue
        if ($import === null) {
            return 1;
        }

        // Import failed
        if ($import->getStatus() === $import::FAILED) {
            $output->writeln('<error>'.$translator->trans(
                'mautic.lead.import.failed',
                [
                    '%reason%' => $import->getStatusInfo(),
                ]
            ).'</error>');

            return 1;
        }

        // echo '<pre>';
        // var_dump($progress, $import);
        // die('</pre>');

        $output->writeln('<info>'.$translator->trans(
            'mautic.lead.import.result',
            [
                '%lines%'   => $import->getLineCount(),
                '%created%' => $import->getInsertedCount(),
                '%updated%' => $import->getUpdatedCount(),
                '%ignored%' => $import->getIgnoredCount(),
            ]
        ).'</info>');

        // if ('dev' == MAUTIC_ENV) {
        //     $output->writeln('<comment>Debug</comment>');
        //     $debug = $event->getDebug();

        //     foreach ($debug as $key => $query) {
        //         $output->writeln("<info>$key</info>");
        //         $output->writeln($query);
        //     }
        // }

        return 0;
    }
}

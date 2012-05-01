<?php
/**
 * This file is part of the Symfttpd Project
 *
 * (c) Laurent Bachelier <laurent@bachelier.name>
 * (c) Benjamin Grandfond <benjamin.grandfond@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfttpd\Command;

use Symfttpd\Symfttpd;
use Symfttpd\Tail\MultiTail;
use Symfttpd\Tail\Tail;
use Symfttpd\PosixTools;
use Symfttpd\Server\Lighttpd;
use Symfttpd\Console\Application;
use Symfttpd\Command\Command;
use Symfttpd\Server\ServerInterface;
use Symfttpd\Configuration\SymfttpdConfiguration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Spork\Deferred\DeferredFactory;
use Spork\ProcessManager;

/**
 * SpawnCommand class
 *
 * @author Laurent Bachelier <laurent@bachelier.name>
 * @author Benjamin Grandfond <benjamin.grandfond@gmail.com>
 */
class SpawnCommand extends Command
{
    /**
     * @var string
     */
    protected $server;

    protected function configure()
    {
        $this->setName('spawn');
        $this->setDescription('Launch the webserver.');

        $this->addOption('default', null, InputOption::VALUE_OPTIONAL, 'Change the default application.', 'index');
        $this->addOption('only', null, InputOption::VALUE_OPTIONAL, 'Do not allow any other application.', false);
        $this->addOption('allow', null, InputOption::VALUE_OPTIONAL, 'Useful with `only`, allow some other applications (useful for allowing a _dev alternative, for example).', false);
        $this->addOption('nophp', null, InputOption::VALUE_OPTIONAL, 'Deny PHP execution in the specified directories (default being uploads).', 'uploads');
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Path of the web directory. Autodected to ../web if not present.', getcwd() . '/web');
        $this->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to listen', 4042);
        $this->addOption('bind', 'b', InputOption::VALUE_OPTIONAL, 'The address to bind', '127.0.0.1');
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Bind on all addresses');
        $this->addOption('tail', 't', InputOption::VALUE_NONE, 'Print the log in the console');
        $this->addOption('kill', 'K', InputOption::VALUE_NONE, 'Kill existing running symfttpd');

        if (function_exists('pcntl_fork')) {
            $this->addOption('single_process', 's', InputOption::VALUE_OPTIONAL, 'Run symfttpd in another process', false);
        }
    }

    /**
     * Run the SYmttpd configured server.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->writeVersion($output);

        // Initialise Server options
        $this->server = $this->getSymfttpd()->getServer($this->getProjectPath());
        $this->server->clear();
        $this->server->configuration->set('port', $input->getOption('port'));
        $this->server->configuration->set('bind', $input->getOption('bind'));

        $this->getConfiguration()->set('restartfile', $this->server->getCacheDir().'/.symfttpd_restart');

        // Kill other running server in the current project.
        if ($input->getOption('kill')) {
            // Kill existing symfttpd instance if found.
            if (file_exists($this->getConfiguration()->get('restartfile'))) {
                unlink($this->getConfiguration()->get('restartfile'));
            }
            exit(!\Symfttpd\Utils\PosixTools::killPid($this->server->configuration->get('pidfile'), $output));
        }

        $this->server->configuration->add($this->getServerOptions($input->getOptions()));

        if ($this->getConfiguration()->has('lighttpd_cmd')) {
            $this->server->setCommand($this->getConfiguration()->get('lighttpd_cmd'));
        }

        // Creates the server configuration.
        $this->server->generate($this->getConfiguration());
        $this->server->write();

        $boundAddress = $input->getOption('all') ? 'all-interfaces' : $input->getOption('bind');

        $bind = $input->getOption('all') ? : $input->getOption('bind');
        $host = in_array($bind, array(false, '0.0.0.0', '::'), true) ? 'localhost' : $bind;

        $apps = array();
        foreach ($this->server->configuration->get('phps') as $file) {
            if (preg_match('/.+\.php$/', $file)) {
                $apps[$file] = ' http://' . $host . ':' . $input->getOption('port') . '/<info>' . $file . '</info>';
            }
        }

        // Pretty information. Nothing interesting code-wise.
        $text = <<<TEXT
lighttpd started on <info>%s</info>, port <info>%s</info>.

Available applications:
%s

<important>Press Ctrl+C to stop serving.</important>

TEXT;
        $output->getFormatter()->setStyle('important', new OutputFormatterStyle('yellow', null, array('bold')));
        $output->write(sprintf($text, $boundAddress, $this->server->configuration->get('port'), implode("\n", $apps)));

        flush();

        if (true == $input->getOption('single_process')) {
            // Run lighttpd
            $this->server->start();

            $output->write('Terminated.');

            return 0;
        }

        $this->spawn($input, $output);

        return 0;
    }

    /**
     * Launch the server in a fork.
     * The parent thread check every seconds if the rewrite
     * rules changed. In this case it will create a file that
     * will tell to the fork that the server must be restarted.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function spawn(InputInterface $input, OutputInterface $output)
    {
        $multitail = null;
        if ($input->getOption('tail')) {
            $logDir = $this->server->getLogDir();
            $multitail = new MultiTail(new OutputFormatter(true));
            $multitail->add('access', new Tail($logDir . '/access.log'), new OutputFormatterStyle('blue'));
            $multitail->add('error', new Tail($logDir . '/error.log'), new OutputFormatterStyle('red', null, array('bold')));
            // We have to do it before the fork to capture the startup messages
            $multitail->consume();
        }

        // Callback that will start the server.
        $forkCallback = function(ServerInterface $server, SymfttpdConfiguration $configuration) use ($output)
        {
            do {
                if (file_exists($configuration->get('restartfile'))) {
                    unlink($configuration->get('restartfile'));
                }

                // Run lighttpd
                $server->start();

                if (!file_exists($configuration->get('restartfile'))) {
                    $output->writeln('Terminated.');
                } else {
                    $output->writeln('<info>Something in web/ changed. Restarting lighttpd.</info>');

                    // Regenerate the lighttpd configuration
                    $server->generateRules($configuration);
                }
            } while (file_exists($configuration->get('restartfile')));
        };

        // Create variables for the callback
        $server = $this->server;
        $configuration = $this->getConfiguration();

        // Callback to execute in parallel of the child process.
        $callback = function () use ($input, $output, $server, $configuration, $multitail)
        {
            $filesystem = new \Symfttpd\Filesystem\Filesystem();
            $prevGenconf = null;
            while (false !== sleep(1)) {
                // Generate the configuration file.
                $server->generateRules($configuration);
                $genconf = $server->read();

                if ($prevGenconf !== null && $prevGenconf !== $genconf) {
                    // This sleep() is so that if a HTTP request just created a file in web/,
                    // the web server isn't restarted right away.
                    sleep(1);
                    $filesystem->touch($configuration->get('restartfile'));
                    \Symfttpd\Utils\PosixTools::killPid($server->configuration->get('pidfile'), $output);
                }
                $prevGenconf = $genconf;

                if ($multitail instanceof MultiTail) {
                    $multitail->consume();
                }
            }
        };

        $manager = new ProcessManager(new DeferredFactory());
        $manager->fork($forkCallback, array($this->server, $this->getConfiguration()))
            ->always($callback)
            ->resolve();
    }

    /**
     * Return the project path.
     *
     * @return string
     */
    protected function getProjectPath()
    {
        return getcwd();
    }

    /**
     * @return \Symfttpd\Configuration\SymfttpdConfiguration
     */
    public function getConfiguration()
    {
        return $this->getSymfttpd()->getConfiguration();
    }

    /**
     * Create the server options passed to the command.
     *
     * @param array $options
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getServerOptions(array $options)
    {
        $allow = explode(',', $options['allow']);
        $nophp = explode(',', $options['nophp']);
        $path  = realpath($options['path']);

        $files = array(
            'dir' => array(),
            'php' => array(),
            'file' => array()
        );

        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('Directory "%s" not found.', $options['path']));
        }

        foreach (new \DirectoryIterator($path) as $file) {
            $name = $file->getFilename();
            if ($name[0] != '.') {
                if ($file->isDir()) {
                    $files['dir'][] = $name;
                }
                elseif (!preg_match('/\.php$/', $name)) {
                    $files['file'][] = $name;
                }
                elseif (empty($options['only'])) {
                    $files['php'][] = $name;
                }
            }
        }

        foreach ($allow as $name) {
            $files['php'][] = $name . '.php';
        }

        return array(
            'document_root' => $path,
            'nophp'    => $nophp,
            'default'  => $options['default'],
            'phps'     => $files['php'],
            'files'    => $files['file'],
            'dirs'     => $files['dir'],
        );
    }
}

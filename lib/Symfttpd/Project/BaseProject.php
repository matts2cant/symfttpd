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

namespace Symfttpd\Project;

use Symfttpd\Project\Exception\ProjectException;

/**
 * BaseProject class
 *
 * @author Benjamin Grandfond <benjaming@theodo.fr>
 */
abstract class BaseProject implements ProjectInterface
{
    static public $configurationKeys = array(
        'project_readable_dirs',     // readable directories by the server in the web dir.
        'project_readable_files',    // readable files by the server in the web dir (robots.txt).
        'project_readable_phpfiles', // executable php files in the web directory (index.php)
        'project_readable_restrict', // true if no other php files are readable than configured ones or index file.
        'project_nophp',             // deny PHP execution in the specified directories (default being uploads).
        'project_log_dir',
        'project_cache_dir',
        'project_web_dir',
    );

    /**
     * The name of the project framework.
     *
     * @var string
     */
    protected $name;

    /**
     * The version of the project framework.
     *
     * @var string
     */
    protected $version;

    /**
     * Directory contained by the web dir, accessible
     * by the web user.
     *
     * @var Array
     */
    public $readableDirs = array();

    /**
     * Files contained by the web dir, accessible
     * by the web user.
     *
     * @var Array
     */
    public $readableFiles = array();

    /**
     * Php executable for the application.
     *
     * @var Array
     */
    public $readablePhpFiles = array();

    /**
     * @var String
     */
    protected $rootDir;

    /**
     * @var \Symfttpd\OptionBag
     */
    public $options;

    public function __construct(\Symfttpd\OptionBag $options, $path = null)
    {
        $this->rootDir = $path;
        $this->options = $options;

        $this->validate('project_readable_dirs');
        $this->validate('project_readable_files');
        $this->validate('project_readable_phpfiles');
    }

    /**
     * Scan readable files, dirs and php executable files
     * as index.php.
     */
    public function scan()
    {
        // Reset the default values.
        $this->readableDirs = $this->options->get('project_readable_dirs', array());
        $this->readableFiles = $this->options->get('project_readable_files', array());
        $this->readablePhpFiles = $this->options->get('project_readable_phpfiles', array('index.php'));

        $iterator = new \DirectoryIterator($this->getWebDir());

        foreach ($iterator as $file) {
            $name = $file->getFilename();
            if ($name[0] != '.') {
                if ($file->isDir() && false == in_array($name, $this->readableDirs)) {
                    $this->readableDirs[] = $name;
                } elseif (!preg_match('/\.php$/', $name) && false == in_array($name, $this->readableFiles)) {
                    $this->readableFiles[] = $name;
                } else {
                    if (false === $this->options->has('project_readable_restrict')
                        && false == in_array($name, $this->readablePhpFiles)) {
                        $this->readablePhpFiles[] = $name;
                    }
                }
            }
        }

        sort($this->readableDirs);
        sort($this->readableFiles);
        sort($this->readablePhpFiles);
    }

    /**
     * Validate an option that contains file or directories.
     *
     * @param $option
     */
    public function validate($option)
    {
        $options = $this->options->get($option, array());

        foreach ($options as $name => $value) {
            if (false == file_exists($this->getWebDir().'/'.$value)) {
                unset($options[$name]);
            }
        }

        $this->options->set($option, $options);
    }

    /**
     * Return the directory where lives the project.
     *
     * @return mixed
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * Set the directory where lives the project.
     *
     * @param $rootDir
     * @throws \InvalidArgumentException
     */
    public function setRootDir($rootDir)
    {
        $realDir = realpath($rootDir);

        if (false == $realDir) {
            throw new \InvalidArgumentException(sprintf('The path "%s" does not exist', $rootDir));
        }

        $this->rootDir = $realDir;
    }

    /**
     * Return the name of the project.
     *
     * @return string
     * @throws \Symfttpd\Project\Exception\ProjectException
     */
    public function getName()
    {
        if (null == $this->name) {
            throw new ProjectException('The name must be set.');
        }

        return $this->name;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        if (null == $this->version) {
            throw new ProjectException('The version must be set.');
        }

        return $this->version;
    }
}

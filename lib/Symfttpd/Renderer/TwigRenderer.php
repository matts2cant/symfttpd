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

namespace Symfttpd\Renderer;

/**
 * TwigRenderer class.
 *
 * @author Benjamin Grandfond <benjamin.grandfond@gmail.com>
 */
class TwigRenderer implements RendererInterface
{
    /**
     * {@inheritdoc}
     */
    public function render($skeletonDir, $template, $parameters = array())
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Filesystem($skeletonDir), array(
            'debug'            => true,
            'cache'            => false,
            'strict_variables' => true,
            'autoescape'       => false,
        ));

        $twig->addFunction('sys_get_temp_dir', new \Twig_Function_Function('sys_get_temp_dir'));
        $twig->addFunction('in_array', new \Twig_Function_Function('in_array'));

        $twig->addFilter('preg_quote', new \Twig_Filter_Function('preg_quote'));

        return $twig->render($template, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function renderFile($skeletonDir, $template, $target, $parameters = array())
    {
        file_put_contents($target, $this->render($skeletonDir, $template, $parameters));
    }
}
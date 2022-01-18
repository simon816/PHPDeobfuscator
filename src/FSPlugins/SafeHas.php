<?php
namespace FSPlugins;

use League\Flysystem\Plugin\AbstractPlugin;

class SafeHas extends AbstractPlugin
{
    public function getMethod()
    {
        return 'safeHas';
    }

    public function handle($path)
    {
        try {
            return $this->filesystem->has($path);
        } catch (\LogicException $e) {
            return false;
        }
    }
}

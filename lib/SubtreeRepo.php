<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\GithubSubtreeTool\Lib;

class SubtreeRepo extends AbstractRepo
{
    /**
     * Copies the composer.json to the given master repo.
     *
     * @param MasterRepo $masterRepo
     */
    public function copyComposerToMaster(MasterRepo $masterRepo)
    {
        $composerPath = $this->getRepoPath() . 'composer.json';
        $masterComposerPath = $masterRepo->getRepoPath() . GIT_SUBTREE . '/' . $this->getRepo() . '/composer.json';
        copy($composerPath, $masterComposerPath);
    }
}

<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\GithubSubtreeTool\Lib;

class MasterRepo extends AbstractRepo
{

    /**
     * Copies the master repo to the given destination subtree repo.
     *
     * @param SubtreeRepo $destinationRepo
     */
    public function copySubtreeRepo(SubtreeRepo $destinationRepo)
    {
        $checkoutFolder = REPO_DIR;
        $source = $checkoutFolder . $this->getRepo() . '/' . $this->getBranch(
            ) . '/' . GIT_SUBTREE . '/' . $destinationRepo->getRepo();
        if (!is_dir($source)) {
            die('source repository not found: ' . $source);
        }
        $destination = $checkoutFolder . $destinationRepo->getRepo() . '/' . $destinationRepo->getBranch() . '/';
        $command = 'cp -rp ' . $source . '/* ' . $destination;
        System::command($command);
    }

    /**
     * Lists subtree components on the master repo.
     *
     * @return array
     */
    public function getSubtreeComponents()
    {
        // get the subtree components
        $subTreeRaw = $this->_client->api('repo')->contents()->show(GIT_ACC, GIT_REPO, GIT_SUBTREE, $this->_branch);
        $subTrees = [];
        foreach ($subTreeRaw as $st) {
            if ($st['type'] == 'dir') {

                // create new repo instance
                $repoInstance = new SubtreeRepo($st['name'], 'master');
                $branches = $repoInstance->getBranches();
                foreach ($branches as $b) {
                    if ($this->_branch == $b) {
                        $repoInstance->setBranch($b);
                    }
                }
                $subTrees[] = $repoInstance;
            }
        }

        return $subTrees;
    }
}

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
        $source = $checkoutFolder . $this->getRepo() . '/' . $this->getBranch() . '/' . GIT_SUBTREE . '/' . $destinationRepo->getRepo();
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

    /**
     * Returns a list of composer libraries used inside the repo.
     *
     * @return array|void
     */
    public function getComposerRepoLibraries()
    {
        $repoPath = $this->getRepoPath();
        $components = $this->getSubtreeComponents();
        $composerLibs = [];
        foreach ($components as $c) {
            // get composer json data
            $composerPath = $repoPath . GIT_SUBTREE . '/' . $c->getRepo() . '/composer.json';
            if (!file_exists($composerPath)) {
                $cli = new Cli;
                $cli->error('composer.json doesn\t exist for ' . $this->_repo . ' (' . $this->_branch . ')');

                return;
            }
            $composerData = json_decode(file_get_contents($composerPath), true);

            $composerLibs[] = $composerData['name'];
        }

        // master
        $composerPath = $repoPath . '/composer.json';
        if (!file_exists($composerPath)) {
            $cli = new Cli;
            $cli->error('composer.json doesn\t exist for ' . $this->_repo . ' (' . $this->_branch . ')');

            return;
        }
        $composerData = json_decode(file_get_contents($composerPath), true);

        $composerLibs[] = $composerData['name'];

        return $composerLibs;
    }
}

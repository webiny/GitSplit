<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\GithubSubtreeTool\Lib;

abstract class AbstractRepo
{
    /**
     * @var string Current repo name.
     */
    protected $_repo;

    /**
     * @var string Current branch.
     */
    protected $_branch;

    /**
     * @var \Github\Client
     */
    protected $_client;

    /**
     * @var array List of branches on the repo.
     */
    protected $_branches;

    /**
     * @var array List of tags on the repo.
     */
    protected $_tags;

    /**
     * @var array List of composer libraries on the repo.
     */
    protected $_composerLibs;


    /**
     * Base constructor.
     *
     * @param $repo
     * @param $branch
     */
    public function __construct($repo, $branch)
    {
        $this->_repo = $repo;
        $this->_branch = $branch;

        // initialize the github client
        $this->_client = new \Github\Client(new \Github\HttpClient\CachedHttpClient([
            'cache_dir' => REPO_DIR . 'gh-cache/' . $this->_repo
        ]));
        $this->_client->authenticate(GIT_USER, GIT_PASS, \Github\Client::AUTH_HTTP_PASSWORD);
    }

    /**
     * Set branch.
     *
     * @param $branch
     */
    public function setBranch($branch)
    {
        $this->_branch = $branch;
    }

    /**
     * Return the branch name.
     *
     * @return string
     */
    public function getBranch()
    {
        return $this->_branch;
    }

    /**
     * Return the repo name.
     *
     * @return string
     */
    public function getRepo()
    {
        return $this->_repo;
    }

    /**
     * List all branches under the repo.
     *
     * @return array
     */
    public function getBranches()
    {
        if (empty($this->_branches)) {
            $branches = $this->_client->api('repo')->branches(GIT_ACC, $this->_repo);
            foreach ($branches as $b) {
                $this->_branches[] = $b['name'];
            }
        }

        return $this->_branches;
    }

    /**
     * Does the branch exist in the repo.
     *
     * @param $branch
     *
     * @return bool
     */
    public function branchExists($branch)
    {
        if (!in_array($branch, $this->_branches)) {
            return false;
        }

        return true;
    }

    /**
     * Get the latest branch.
     *
     * @return mixed|string
     */
    public function getLatestBranch()
    {
        $latestBranch = '0.0.0';
        $branches = $this->getBranches();

        foreach ($branches as $b) {
            $branch = str_replace('v', '', $b);
            if (preg_match('/(\d{1,10}\.\d{1,10})/', $branch) && version_compare($branch, $latestBranch, '>')) {
                $latestBranch = $branch;
            }
        }

        return $latestBranch;
    }

    /**
     * List all tags on the repo.
     *
     * @return array
     */
    public function getTags()
    {
        if (empty($this->_tags)) {
            $tags = $this->_client->api('repo')->tags(GIT_ACC, $this->_repo);
            foreach ($tags as $t) {
                $this->_tags[] = $t['name'];
            }
        }

        return $this->_tags;
    }

    /**
     * Get the latest tag.
     *
     * @return mixed|string
     */
    public function getLatestTag()
    {
        $latestTag = '0.0.0';
        $tags = $this->getTags();

        foreach ($tags as $t) {
            $tag = str_replace('v', '', $t);
            if (preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $tag) && version_compare($tag, $latestTag, '>')) {
                $latestTag = $tag;
            }
        }

        return $latestTag;
    }

    /**
     * Clone repo.
     */
    public function cloneRepo()
    {
        // checkout root
        $repoPath = $this->getRepoPath();

        // we always do a fresh copy
        System::removeResource($repoPath);

        // clone the repo
        $checkoutRepo = 'https://' . GIT_USER . ':' . GIT_PASS . '@github.com/' . GIT_ACC . '/' . $this->_repo;
        $command = 'git clone ' . $checkoutRepo . '.git -b' . $this->_branch . ' ' . $repoPath;
        System::command($command);
    }

    /**
     * Remove all the files inside the repo, except the '.git'.
     */
    public function resetRepo()
    {
        $repoPath = $this->getRepoPath();

        $path = realpath($repoPath);
        $h = opendir($path);
        while (($resource = readdir($h))) {
            if ($resource != '.' && $resource != '..' && $resource != '.git') {
                System::removeResource($path . '/' . $resource);
            }
        }
    }

    /**
     * Creates a new branch.
     *
     * @param $newBranch
     */
    public function createBranch($newBranch)
    {
        $this->_branches[] = $newBranch;

        $repoPath = $this->getRepoPath();
        $command = '(cd ' . $repoPath . '; git checkout -b ' . $newBranch . ')';
        System::command($command);
    }

    /**
     * Checkouts the given new branch.
     *
     * @param $branch
     */
    public function checkoutBranch($branch)
    {
        $this->createBranch($branch);
    }

    /**
     * Checkout an existing branch.
     *
     * @param $branch
     */
    public function checkoutExistingBranch($branch)
    {
        $repoPath = $this->getRepoPath();
        $command = '(cd ' . $repoPath . '; git checkout ' . $branch . ')';
        System::command($command);
    }

    /**
     * Merges the two given branches.
     *
     * @param $mergeTo
     * @param $mergeFrom
     */
    public function mergeBranch($mergeTo, $mergeFrom)
    {
        $repoPath = $this->getRepoPath();

        // fetch
        $command = '(cd ' . $repoPath . '; git fetch)';
        System::command($command);

        // checkout the mergeTo branch
        $command = '(cd ' . $repoPath . '; git checkout ' . $mergeTo . ')';
        echo $command . "\n";
        System::command($command);

        // pull
        $command = '(cd ' . $repoPath . '; git pull)';
        echo $command . "\n";
        System::command($command);

        // issue the merge command
        $command = '(cd ' . $repoPath . '; git merge ' . $mergeFrom . ')';
        echo $command . "\n";
        System::command($command);
    }

    /**
     * Creates a new tag.
     *
     * @param $newTag
     */
    public function createTag($newTag)
    {
        $repoPath = $this->getRepoPath();
        $command = '(cd ' . $repoPath . '; git tag ' . 'v' . $newTag . ')';
        System::command($command);
    }

    /**
     * Commits all current changes.
     *
     * @param $msg
     */
    public function commitRepo($msg)
    {
        $repoPath = $this->getRepoPath();

        // git add
        $command = '(cd ' . $repoPath . '; git add -A)';
        System::command($command);

        // git commit
        $command = '(cd ' . $repoPath . '; git commit -m "' . $msg . '")';
        System::command($command);
    }

    /**
     * Pushes the changes to git server.
     *
     * @param $branchToPush
     */
    public function pushRepo($branchToPush)
    {
        $repoPath = $this->getRepoPath();

        // push the commit
        $command = '(cd ' . $repoPath . '; git push origin ' . $branchToPush . ')';
        System::command($command);

        // push tags
        $command = '(cd ' . $repoPath . '; git push --tags)';
        System::command($command);
    }

    /**
     * Updates the versions on the current composer.json.
     *
     * @param $libs
     * @param $newVersion
     * @param $devVersion
     */
    public function updateComposerRepoLibraryVersion($libs, $newVersion, $devVersion)
    {
        $repoPath = $this->getRepoPath();

        // get composer json data
        $composerPath = $repoPath . 'composer.json';
        if (!file_exists($composerPath)) {
            $cli = new Cli;
            $cli->error('composer.json doesn\t exist for ' . $this->_repo . ' (' . $this->_branch . ')');

            return;
        }

        $composerData = json_decode(file_get_contents($composerPath), true);

        // do the update of libs
        $composerDepths = [
            'require',
            'require-dev'
        ];
        foreach ($composerDepths as $cd) {
            foreach ($composerData[$cd] as $dep => $v) {
                if (in_array($dep, $libs)) {
                    $composerData[$cd][$dep] = $newVersion;
                }
            }
        }
        // update the dev version
        $composerData['extras']['branch-alias']['dev-master'] = $devVersion;

        // write the new composer json
        file_put_contents($repoPath . 'composer.json',
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Delete the given branch locally.
     *
     * @param $branchToDelete
     */
    public function deleteBranch($branchToDelete)
    {
        $repoPath = $this->getRepoPath();

        // delete tehe tag
        $command = '(cd ' . $repoPath . '; git branch -D ' . $branchToDelete . ')';
        System::command($command);
    }

    /**
     * Delete a remote branch.
     *
     * @param $branchToDelete
     */
    public function pushBranchDelete($branchToDelete)
    {
        $repoPath = $this->getRepoPath();

        // delete tehe tag
        $command = '(cd ' . $repoPath . '; git push origin --delete ' . $branchToDelete . ')';
        System::command($command);
    }

    /**
     * Delete the given tag locally.
     *
     * @param $tagToDelete
     */
    public function deleteTag($tagToDelete)
    {
        $repoPath = $this->getRepoPath();

        // delete the tag
        $command = '(cd ' . $repoPath . '; git tag -d ' . $tagToDelete . ')';
        System::command($command);
    }

    /**
     * Delete a remote tag.
     *
     * @param $tagToDelete
     */
    public function pushTagDelete($tagToDelete)
    {
        $repoPath = $this->getRepoPath();

        // delete the tag
        $command = '(cd ' . $repoPath . '; git push --delete origin ' . $tagToDelete . ')';
        System::command($command);
    }

    /**
     * Return the repo path on the disk.
     *
     * @return string
     */
    public function getRepoPath()
    {
        return REPO_DIR . $this->_repo . '/' . $this->_branch . '/';
    }

}

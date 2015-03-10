<?php
namespace Webiny\Lib;

class Repo
{
    private $_repo;
    private $_branch;
    private $_client;
    private $_branches;
    private $_tags;
    private $_composerLibs;
    
    public function construct($repo, $branch)
    {
        $this->_repo = $repo;
        $this->_branch = $branch;
        
        // initialize the github client
        $this->_client = new \Github\Client(new \Github\HttpClient\CachedHttpClient(['cache_dir' => REPO_DIR]));
        $this->_client->authenticate(GIT_USER, GIT_PASS, \Github\Client::AUTH_HTTP_PASSWORD);
    }
    
    public function getBranches()
    {
        if(empty($this->_branches)){
            $this->_branches = $client->api('repo')->branches(GIT_ACC, $this->_repo);
        }
        
        return $this->_branches;
    }
    
    public function getLatestBranch()
    {
        $latestBranch = '0.0.0';
        $branches = $this->getBranches();
        
        foreach ($branches as $b) {
            $branch = str_replace('v', '', $b['name']);
            if (preg_match('/(\d{1,10}\.\d{1,10})/', $branch) && version_compare($branch, $latestBranch, '>')) {
                $latestBranch = $branch;
            }
        }
        
        return $latestBranch;
    }
    
    public function getTags()
    {
        if(empty($this->_tags)){
            $this->_tags = $client->api('repo')->tags(GIT_ACC, $this->_repo);
        }
        
        return $this->_tags;
    }
    
    public function getLatestTag()
    {
        $latestTag = '0.0.0';
        $tags = $this->getTags();
        
        foreach ($tags as $t) {
            $tag = str_replace('v', '', $t['name']);
            if (preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $tag) && version_compare($tag, $latestTag, '>')) {
                $latestTag = $tag;
            }
        }
        
        return $latestTag;
    }
    
    public function cloneRepo()
    {
        // checkout root
        $repoPath = $this->getRepoPath();
        
        // we always do a fresh copy
        System::removeResource($repoPath);
        
        // clone the repo
        $checkoutRepo = 'https://' . GIT_USER . ':' . GIT_PASS . '@github.com/' . GIT_ACC . '/' . $this->_repo;
        $command = 'git clone ' . $checkoutRepo . '.git -b' . $this->branch . ' ' . $repoPath;
        System::command($command);
    }
    
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
    
    //@TODO
    public function copyRepo($sourceRepo, $sourceBranch, $destinationRepo, $destinationBranch)
    {
        $checkoutFolder = __DIR__ . '/../repos/';
        $source = $checkoutFolder . $sourceRepo . '/' . $sourceBranch . '/' . GIT_SUBTREE . '/' . $destinationRepo;
        if (!is_dir($source)) {
            die('source repository not found: ' . $source);
        }
        $destination = $checkoutFolder . $destinationRepo . '/' . $destinationBranch . '/';
        $command = 'cp -rp ' . $source . '/* ' . $destination;
        system($command);
    }
    
    public function createBranch($newBranch)
    {
        $repoPath = $this->getRepoPath();
        $command = '(cd ' . $repoPath . '; git checkout -b ' . $newBranch . ')';
        System::command($command);
    }
    
    public function createTag($newTag)
    {
        $repoPath = $this->getRepoPath();
        $command = '(cd ' . $repoPath . '; git tag ' . 'v' . $newTag . ')';
        System::command($command);
    }
    
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

    public function getComposerRepoLibraries()
    {
        if(!empty($this->_composerLibs)){
            return $this->_composerLibs;
        }
        
        $repoPath = $this->getRepoPath();
        
        // get composer json data
        $composerPath = $repoPath . 'composer.json';
        if (!file_exists($composerPath)) {
            $cli = new Cli;
            $cli->error('composer.json doesn\t exist for ' . $this->_repo . ' (' . $this->_branch . ')');
            
            return;
        }
        
        $composerData = json_decode(file_get_contents($composerPath), true);
        
        // get the composer lib name
        $libName = explode('/', $composerData['name']);
        $libName = $libName[0];
        
        // parse the repo libraries
        $repoLibs = [];
        $composerDepths = [
            'require',
            'require-dev'
        ];
        foreach ($composerDepths as $cd) {
            foreach ($composerData[$cd] as $dep => $v) {
                if (strpos($dep, $libName . '/') !== false) {
                    $repoLibs[] = $dep;
                }
            }
        }
        
        $this->_composerLibs = $repoLibs;
        
        return $this->_composerLibs;
    }
    
    public function updateComposerRepoLibraryVersion($newVersion, $devVersion)
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
                if (in_array($dep, $this->getComposerRepoLibraries())) {
                    $composerData[$cd][$dep] = $newVersion;
                }
            }
        }
        // update the dev version
        $composerData['extras']['branch-alias']['dev-master'] = $devVersion;
        
        // write the new composer json
        file_put_contents($repoPath . 'composer.json',
                          json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
    
    //@TODO
    public function copyComposerToMaster($repo, $repoBranch, $masterBranch)
    {
        $composerPath = getRepoPath($repo, $repoBranch) . 'composer.json';
        $masterComposerPath = getRepoPath(GIT_REPO, $masterBranch) . GIT_SUBTREE . '/' . $repo . '/composer.json';
        copy($composerPath, $masterComposerPath);
    }
    
    public function getRepoPath()
    {
        return REPO_DIR . $this->_repo . '/' . $this->_branch . '/';
    }
}

<?php

function cloneRepo($repo, $branch)
{
    // checkout root
    $repoPath = getRepoPath($repo, $branch);

    // we always do a fresh checkout
    if (is_dir($repoPath)) {
        $command = 'rm -rf ' . $repoPath;
        system($command);
    }

    $checkoutRepo = 'https://' . GIT_USER . ':' . GIT_PASS . '@github.com/' . GIT_ACC . '/' . $repo;
    $command = 'git clone ' . $checkoutRepo . '.git -b' . $branch . ' ' . $repoPath;
    system($command);
}

function resetRepo($repo, $branch)
{
    $repoPath = getRepoPath($repo, $branch);

    $path = realpath($repoPath);
    $h = opendir($path);
    while (($resource = readdir($h))) {
        if ($resource != '.' && $resource != '..' && $resource != '.git') {
            $command = 'rm -rf ' . $path . '/' . $resource;
            system($command);
        }
    }
}

function copyRepo($sourceRepo, $sourceBranch, $destinationRepo, $destinationBranch)
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

function createBranch($repo, $branch, $newBranch)
{
    $repoPath = getRepoPath($repo, $branch);

    $command = '(cd ' . $repoPath . '; git checkout -b ' . $newBranch . ')';
    system($command);
}

function createTag($repo, $branch, $newTag)
{
    $repoPath = getRepoPath($repo, $branch);

    // create new release (releases are always created)
    $command = '(cd ' . $repoPath . '; git tag ' . 'v' . $newTag . ')';
    system($command);
}

function commitRepo($repo, $branch, $msg)
{
    $repoPath = getRepoPath($repo, $branch);

    // git add
    $command = '(cd ' . $repoPath . '; git add -A)';
    system($command);

    // git commit
    $command = '(cd ' . $repoPath . '; git commit -m "' . $msg . '")';
    system($command);
}

function pushRepo($repo, $branch, $newRelease)
{
    $repoPath = getRepoPath($repo, $branch);

    // push the commit
    $command = '(cd ' . $repoPath . '; git push origin ' . $newRelease . ')';
    system($command);

    // push tags
    $command = '(cd ' . $repoPath . '; git push --tags)';
    system($command);
}

function getTime()
{
    $timer = explode(' ', microtime());
    $timer = $timer[1] + $timer[0];

    return $timer;
}

function displayTime($seconds)
{
    $seconds = round($seconds);

    if ($seconds < 60) {
        return $seconds . ' sec';
    }

    $minutes = round($seconds / 60);
    $seconds = $seconds % 60;

    return $minutes . ' min ' . $seconds . ' sec';
}

function cliLine($msg = '')
{
    \cli\line($msg);
}

function cliPrompt($question, $default = false, $marker = ': ', $hide = false)
{
    $color = \cli\Colors::color([
                                    'color' => 'yellow'
                                ]
    );

    return \cli\prompt($color . $question . ' [' . $default . ']' . "\033[0m", $default, $marker, $hide);
}

function cliMenu($items, $default = null, $title = 'Choose an item')
{
    $color = \cli\Colors::color([
                                    'color' => 'yellow'
                                ]
    );

    $title = $color . $title;
    if ($default !== null) {
        $title .= ' [' . $items[$default] . ']';
    }
    $title = $title . " \033[0m";

    return \cli\menu($items, $default, $title);
}

function cliSeparator($len = 64, $char = '+')
{
    cliLine(str_repeat($char, $len));
}

function cliError($msg)
{
    $color = \cli\Colors::color([
                                    'color'      => 'black',
                                    'background' => 'red'
                                ]
    );

    \cli\line($color . 'ERROR: ' . $msg . " \033[0m");
}

function getComposerRepoLibraries($repo, $branch)
{
    $repoPath = getRepoPath($repo, $branch);

    // get composer json data
    $composerPath = $repoPath . 'composer.json';
    if (!file_exists($composerPath)) {
        cliError('composer.json doesn\t exist for ' . $repo . ' (' . $branch . ')');

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

    return $repoLibs;
}

function updateComposerRepoLibraryVersion($repo, $branch, $libs, $newVersion, $devVersion)
{
    $repoPath = getRepoPath($repo, $branch);

    // get composer json data
    $composerPath = $repoPath . 'composer.json';
    if (!file_exists($composerPath)) {
        cliError('composer.json doesn\t exist for ' . $repo . ' (' . $branch . ')');

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
                      json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function copyComposerToMaster($repo, $repoBranch, $masterBranch)
{
    $composerPath = getRepoPath($repo, $repoBranch) . 'composer.json';
    $masterComposerPath = getRepoPath(GIT_REPO, $masterBranch) . GIT_SUBTREE . '/' . $repo . '/composer.json';
    copy($composerPath, $masterComposerPath);
}


function getRepoPath($repo, $branch)
{
    return REPO_DIR . $repo . '/' . $branch . '/';
}
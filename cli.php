<?php
// cli check
if (!php_sapi_name() == "cli") {
    die('You must run this script from your command line');
}

// some initial checks
if(GIT_ACC=='' || GIT_REPO == '' || GIT_USER == ''){
    die('You config is invalid, please open and edit config.php');
}

// repo dir
define('REPO_DIR', __DIR__ . '/repos/'); // if changed, make sure you have a trailing slash at the end

// initial requirements
require_once 'config.php';
require_once 'lib/tools.php';
require_once 'vendor/autoload.php';

// some shell coloring
$colorEnd = "\033[0m";
$colorHighlight = \cli\Colors::color([
                                         'color'      => 'black',
                                         'background' => 'green',
                                         'style'      => 1
                                     ]
);
$colorRed = \cli\Colors::color([
                                   'color'      => 'black',
                                   'background' => 'red',
                                   'style'      => 1
                               ]
);

$startTime = getTime();

// initialize the cli
\cli\Colors::enable(); // make it pretty
cliLine();
cliLine($colorHighlight . 'WELCOME TO WEBINY SUBTREE TOOL' . $colorEnd);
cliLine('Released under MIT license.');
cliLine('http://www.webiny.com/ | https://www.github.com/Webiny/');
cliLine();
cliLine('Use at your own risk.');
cliLine('Make sure you know what you are doing,');
cliLine('or you might mess things up - don\'t blame us then.');
cliLine();
cliLine('All bugs and improvements report to:');
cliLine('https://www.github.com/Webiny/GithubSubtreeTool/');
cliSeparator();
cliLine();
cliLine();

// let's start
cliPrompt('Press any key to continue...', 'ENTER', '');

// check the we have the repos dir
if (!is_dir(REPO_DIR)) {
    mkdir(REPO_DIR, 0777);
} else {
    // make sure it's empty
    system('rm -rf ' . REPO_DIR . '*');
}

cliLine('The tool will now gather data about your root repo (' . $colorHighlight . GIT_ACC . '/' . GIT_REPO . $colorEnd . ').'
);
cliPrompt('Press any key to continue...', 'ENTER', '');

// initialize the github client
$client = new \Github\Client(new \Github\HttpClient\CachedHttpClient(['cache_dir' => REPO_DIR]));
$client->authenticate(GIT_USER, GIT_PASS, \Github\Client::AUTH_HTTP_PASSWORD);

// get the tag and branch info
cliLine('...(please wait)');
$branches = $client->api('repo')->branches(GIT_ACC, GIT_REPO);
$tags = $client->api('repo')->tags(GIT_ACC, GIT_REPO);


// extract the latest branch
$latestBranch = '0.0.0';
$branchList = [];
foreach ($branches as $b) {
    $branch = str_replace('v', '', $b['name']);
    if (preg_match('/(\d{1,10}\.\d{1,10})/', $branch) && version_compare($branch, $latestBranch, '>')) {
        $latestBranch = $branch;
    }
    $branchList[] = $b['name'];
}

// extract the latest tag
$latestTag = '0.0.0';
foreach ($tags as $t) {
    $tag = str_replace('v', '', $t['name']);
    if (preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $tag) && version_compare($tag, $latestTag, '>')) {
        $latestTag = $tag;
    }
}

// output the info
cliSeparator();
cliLine('Latest branch: ' . $latestBranch);
cliLine('Latest tag: ' . $latestTag);

// ask the user what does he want to do
cliSeparator();
$menu = [
    '1' => 'Branch',
    '2' => 'Tag',
];

while (true) {
    $choice = cliMenu($menu, null, 'Do you want to create a new branch or a tag');
    cliLine();
    if ($choice) {
        break;
    }
}

$branchOrTag = ($choice === 1) ? 'branch' : 'tag';

// get the next release version
if ($branchOrTag == 'tag') {
    $versionData = explode('.', $latestTag);
    $nextRelease = $versionData[0] . '.' . $versionData[1] . '.' . ($versionData[2] + 1);
} else {
    $nextRelease = $latestBranch + 0.1;
}

while (true) {
    $nextReleaseInput = cliPrompt('Type in the ' . strtoupper($branchOrTag) . ' number you wish to create', $nextRelease
    );

    // validate the next release
    if ($branchOrTag == 'tag' && preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $nextReleaseInput)) {
        break;
    } else if ($branchOrTag == 'branch' && preg_match('/(\d{1,10}\.\d{1,10})/', $nextReleaseInput)) {
        break;
    }
}

cliSeparator();
// from which branch should we create the new release
while (true) {
    $rootBranch = cliMenu($branchList, (count($branchList) - 1),
                          'From which branch should we create the new ' . $branchOrTag
    );
    cliLine();

    if (is_int($rootBranch)) {
        break;
    }
}
$rootBranch = $branchList[$rootBranch];

cliSeparator();
cliLine('Extracting subtree components.');
cliLine('...(please wait)');
cliLine('The following subtree components will be updated:');

// get the subtree components
$subTreeRaw = $client->api('repo')->contents()->show(GIT_ACC, GIT_REPO, GIT_SUBTREE, $rootBranch);
$subTrees = [];
foreach ($subTreeRaw as $st) {
    if ($st['type'] == 'dir') {

        cliSeparator();
        cliLine('Extracting ' . strtoupper($st['name']) . ' details.');
        cliLine('...(please wait)');

        $tbranches = $client->api('repo')->branches(GIT_ACC, $st['name']);
        $ttags = $client->api('repo')->tags(GIT_ACC, $st['name']);

        // extract the latest branch
        $tlatestBranch = '0.0.0';
        $tbranchList = [];
        $rootBranchExists = $colorRed . 'NO' . $colorEnd . ' (using master)';
        $sourceBranch = 'master'; // default
        foreach ($tbranches as $b) {
            $branch = str_replace('v', '', $b['name']);
            if (preg_match('/(\d{1,10}\.\d{1,10})/', $branch) && version_compare($branch, $tlatestBranch, '>')) {
                $tlatestBranch = $branch;
            }
            $tbranchList[] = $b['name'];
            if ($b['name'] == $rootBranch) {
                $rootBranchExists = $colorHighlight . 'YES' . $colorEnd;
                $sourceBranch = $b['name'];
            }
        }

        // extract the latest tag
        $tlatestTag = '0.0.0';
        foreach ($ttags as $t) {
            $tag = str_replace('v', '', $t['name']);
            if (preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $tag) && version_compare($tag, $tlatestTag, '>')) {
                $tlatestTag = $tag;
            }
        }

        $subTrees[] = [
            $st['name'],
            $tlatestBranch,
            $tlatestTag,
            $rootBranchExists,
            $sourceBranch
        ];
    }
}
cliSeparator();
cliLine('For the following components, a new ' . $colorHighlight . strtoupper($branchOrTag
        ) . ' ' . $nextReleaseInput . $colorEnd . ' will be created:'
);
cliSeparator();

$table = new \cli\Table();
$table->setHeaders([
                       'Component',
                       'Latest branch',
                       'Latest tag',
                       'Root branch exists'
                   ]
);
$table->setRows($subTrees);
$table->display();
cliPrompt('Press any key to continue...', 'ENTER', '');

$updateComposer = false;
if ($branchOrTag == 'branch') {
    cliSeparator();
    $menu = [
        'y' => 'Yes',
        'n' => 'No',
    ];
    while (true) {
        $choice = cliMenu($menu, 'n', 'Do you want to also update the version on composer dependencies?');
        cliLine();
        if ($choice) {
            $updateComposer = ($choice == 'y') ? true : false;
            break;
        }
    }

    if ($updateComposer) {
        $composerVersion = cliPrompt('What should be the new version for composer dependencies', '~' . $nextReleaseInput
        );
        $composerDefMaster = cliPrompt('What should be the new dev-master version', $nextReleaseInput . '-dev');
    }
}

// start the update
// first checkout the master repo branch root
cliSeparator();
cliLine('Cloning ' . GIT_REPO . ' (' . $rootBranch . ')');
cloneRepo(GIT_REPO, $rootBranch);
cliLine('Creating new ' . $branchOrTag . ' ' . $nextReleaseInput);

// create the release on master repo and switch to that release
if ($branchOrTag == 'branch') {
    createBranch(GIT_REPO, $rootBranch, $nextReleaseInput);
} else {
    createTag(GIT_REPO, $rootBranch, $nextReleaseInput);
}

// get the list of composer libraries
if ($updateComposer) {
    $repoLibs = getComposerRepoLibraries(GIT_REPO, $rootBranch);
}

// clone the subtree repos
foreach ($subTrees as $st) {
    cliSeparator();
    cliLine($colorHighlight . 'Updating ' . strtoupper($st[0]) . ' component' . $colorEnd);

    // clone repo
    cloneRepo($st[0], $st[4]);

    // create the release
    if ($branchOrTag == 'branch') {
        createBranch($st[0], $st[4], $nextReleaseInput);
    } else {
        createTag($st[0], $st[4], $nextReleaseInput);
    }

    // delete everything, except the git files from the subtree
    // this is required in case we deleted some file in the master repo, so we don't commit it to the subtree
    resetRepo($st[0], $st[4]);

    // copy from the master to the subtree
    copyRepo(GIT_REPO, $rootBranch, $st[0], $st[4]);

    // update the composer file
    if ($updateComposer) {
        updateComposerRepoLibraryVersion($st[0], $st[4], $repoLibs, $composerVersion, $composerDefMaster);

        // sync the new composer.json with the master repo
        copyComposerToMaster($st[0], $st[4], $rootBranch);
    }
}

// update and commit the composer file on the master repo
if ($updateComposer) {
    // update master composer.json
    updateComposerRepoLibraryVersion(GIT_REPO, $rootBranch, $repoLibs, $composerVersion, $composerDefMaster);
}


// ask the confirmation before the push
cliSeparator();
$menu = [
    1 => 'Push changes',
    2 => 'Cancel',
];
while (true) {
    $choice = cliMenu($menu, null, 'All the changes are ready, please confirm your action');
    cliLine();
    if ($choice) {
        if ($choice === 2) {
            cliLine('Action canceled.');
            die();
        }
        break;
    }
}


cliLine($colorHighlight . 'Pushing changes: ' . GIT_ACC . '/' . GIT_REPO . $colorEnd);

// commit the changes
commitRepo(GIT_REPO, $rootBranch, 'Created new ' . $branchOrTag . ' ' . $nextReleaseInput);

// if we created a new branch, we also need a new tag for it
if ($branchOrTag == 'branch') {
    createTag(GIT_REPO, $rootBranch, $nextReleaseInput . '.0');
}

// push the changes
pushRepo(GIT_REPO, $rootBranch, $nextReleaseInput);

foreach ($subTrees as $st) {
    cliLine($colorHighlight . 'Pushing changes: ' . GIT_ACC . '/' . $st[0] . $colorEnd);

    commitRepo($st[0], $st[4], 'Synced changes from master repo.');

    // if we created a new branch, we also need a new tag for it
    if ($branchOrTag == 'branch') {
        createTag($st[0], $st[4], $nextReleaseInput . '.0');
    }

    pushRepo($st[0], $st[4], $nextReleaseInput);
}

// all done
cliSeparator();
cliLine($colorHighlight . 'All done!' . $colorEnd);
cliLine('Execution time: ' . (displayTime(getTime() - $startTime)));
cliSeparator();
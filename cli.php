<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

use Webiny\GithubSubtreeTool\Lib\Cli;
use Webiny\GithubSubtreeTool\Lib\MasterRepo;
use Webiny\GithubSubtreeTool\Lib\System;

// cli check
if (!php_sapi_name() == "cli") {
    die('You must run this script from your command line');
}

// composer
require_once '../../autoload.php';

// initial requirements
require_once 'config.php';

// temp loader
require_once 'lib/Cli.php';
require_once 'lib/AbstractRepo.php';
require_once 'lib/MasterRepo.php';
require_once 'lib/SubtreeRepo.php';
require_once 'lib/System.php';

// repo dir
define('REPO_DIR', __DIR__ . '/repos/'); // if changed, make sure you have a trailing slash at the end

// start time
$startTime = System::getTime();

// initialize the cli
$cli = new Cli();

// print header
$cli->header();

// start the interaction
$cli->prompt('Press any key to continue...', 'ENTER', '');

// create a fresh repo dir
System::removeResource(REPO_DIR . '*');
System::createDir(REPO_DIR);

// confirm with the user the repo
$cli->line('The tool will now gather data about your root repo (' . $cli->colorHighlight(
           ) . GIT_ACC . '/' . GIT_REPO . $cli->colorEnd() . ').'
);
$cli->prompt('Press any key to continue...', 'ENTER', '');

// init Repo instance for master repo
$masterRepo = new MasterRepo(GIT_REPO, 'master');

// get the tag and branch info
$cli->line('...(please wait)');
$branches = $masterRepo->getBranches();
$latestBranch = $masterRepo->getLatestBranch();
$latestTag = $masterRepo->getLatestTag();

// output info
$cli->separator();
$cli->line('Latest branch: ' . $latestBranch);
$cli->line('Latest tag: ' . $latestTag);

// ask the user what does he want to do
$cli->separator();
$menu = [
    '1' => 'Branch',
    '2' => 'Tag',
];
$choice = $cli->menu($menu, null, 'Do you want to create a new branch or a tag');
$branchOrTag = ($choice === 1) ? 'branch' : 'tag';

// get the next release version
if ($branchOrTag == 'tag') {
    $versionData = explode('.', $latestTag);
    $nextRelease = $versionData[0] . '.' . $versionData[1] . '.' . ($versionData[2] + 1);
} else {
    $nextRelease = $latestBranch + 0.1;
}

while (true) {
    $nextReleaseInput = $cli->prompt('Type in the ' . strtoupper($branchOrTag) . ' number you wish to create',
                                     $nextRelease
    );

    // validate the next release
    if ($branchOrTag == 'tag' && preg_match('/(\d{1,10}\.\d{1,10}\.\d{1,10})/', $nextReleaseInput)) {
        break;
    } else if ($branchOrTag == 'branch' && preg_match('/(\d{1,10}\.\d{1,10})/', $nextReleaseInput)) {
        break;
    }
}

// from which branch should we create the new release
$cli->separator();
$rootBranch = $cli->menu($branches, (count($branches) - 1), 'From which branch should we create the new ' . $branchOrTag
);
$rootBranch = $branches[$rootBranch];
$masterRepo->setBranch($rootBranch); // update the branch on the master Repo instance

// extract the subtree components
$cli->line('...(please wait)');
$subTrees = $masterRepo->getSubtreeComponents();

// display the subtree components
$cli->line('For the following components, a new ' . $cli->colorHighlight() . strtoupper($branchOrTag
           ) . ' ' . $nextReleaseInput . $cli->colorEnd() . ' will be created:'
);

$rows = [];
foreach ($subTrees as $st) {
    $rows[] = [
        $st->getRepo(),
        $st->getLatestBranch(),
        $st->getLatestTag(),
        $st->getBranch()
    ];
}
$headers = [
    'Component',
    'Latest branch',
    'Latest tag',
    'Working branch'
];
$cli->table($headers, $rows);
$cli->line('NOTE: master branch is selected on a component, if there is no matching source branch');
$cli->prompt('Press any key to continue...', 'ENTER', '');

// ask if we should also update the composer.json files
$updateComposer = false;
if ($branchOrTag == 'branch') {
    $cli->separator();
    $menu = [
        1 => 'Yes',
        2 => 'No',
    ];
    $choice = $cli->menu($menu, 1, 'Do you want to also update the version on composer dependencies?');
    $updateComposer = ($choice === 1) ? true : false;

    // if so, ask the user to provide some detail
    if ($updateComposer) {
        $composerVersion = $cli->prompt('What should be the new version for composer dependencies',
                                        '~' . $nextReleaseInput
        );
        $composerDefMaster = $cli->prompt('What should be the new dev-master version', $nextReleaseInput . '-dev');
    }
}

// clone the master repo branch
$cli->separator();
$cli->line('Cloning ' . $masterRepo->getRepo() . ' (' . $masterRepo->getBranch() . ')');
$masterRepo->cloneRepo();

// create the new branch/tag on master repo
$cli->line('Creating new ' . $branchOrTag . ' ' . $nextReleaseInput);
if ($branchOrTag == 'branch') {
    $masterRepo->createBranch($nextReleaseInput);
} else {
    $masterRepo->createTag($nextReleaseInput);
}

// get the list of composer libraries
// we can do this only if the repo has been cloned
if ($updateComposer) {
    $composerLibs = $masterRepo->getComposerRepoLibraries();
}

// clone the subtree repos
foreach ($subTrees as $st) {
    $cli->separator();
    $cli->line($cli->colorHighlight() . 'Updating ' . strtoupper($st->getRepo()) . ' component' . $cli->colorEnd());

    // clone repo
    $st->cloneRepo();

    // create the release
    if ($branchOrTag == 'branch') {
        $st->createBranch($nextReleaseInput);
    } else {
        $st->createTag($nextReleaseInput);
    }

    // delete everything, except the git files from the subtree
    // this is required in case we deleted some file in the master repo, so we don't commit it to the subtree
    $st->resetRepo();

    // copy from the master to the subtree
    $masterRepo->copySubtreeRepo($st);

    // update the composer file
    if ($updateComposer) {
        $st->updateComposerRepoLibraryVersion($composerLibs, $composerVersion, $composerDefMaster);

        // sync the new composer.json with the master repo
        $st->copyComposerToMaster($masterRepo);
    }
}

// update and commit the composer file on the master repo
if ($updateComposer) {
    // update master composer.json
    $masterRepo->updateComposerRepoLibraryVersion($composerLibs, $composerVersion, $composerDefMaster);
}

// ask the confirmation before the push
$cli->separator();
$menu = [
    1 => 'Push changes',
    2 => 'Cancel',
];
$choice = $cli->menu($menu, null, 'All the changes are ready, please confirm your action');
if ($choice === 2) {
    $cli->line('Action canceled.');
    die();
}

$cli->line($cli->colorHighlight() . 'Pushing changes: ' . GIT_ACC . '/' . GIT_REPO . $cli->colorEnd());

// commit the changes
$masterRepo->commitRepo('Created new ' . $branchOrTag . ' ' . $nextReleaseInput);

// if we created a new branch, we also need a new tag for it
if ($branchOrTag == 'branch') {
    $masterRepo->createTag($nextReleaseInput . '.0');
}

// push the changes
$masterRepo->pushRepo($nextReleaseInput);

// push the changes for subtrees
foreach ($subTrees as $st) {
    $cli->line($cli->colorHighlight() . 'Pushing changes: ' . GIT_ACC . '/' . $st->getRepo() . $cli->colorEnd());

    $st->commitRepo('Synced changes from master repo.');

    // if we created a new branch, we also need a new tag for it
    if ($branchOrTag == 'branch') {
        $st->createTag($nextReleaseInput . '.0');
    }

    $st->pushRepo($nextReleaseInput);
}

// all done
$cli->separator();
$cli->line($cli->colorHighlight() . 'All done!' . $cli->colorEnd());
$cli->line('Execution time: ' . (System::displayTime(System::getTime() - $startTime)));
$cli->separator();
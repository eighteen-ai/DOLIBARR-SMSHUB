<?php
/* Copyright (C) 2026 SMSHUB - Auto-update from GitHub */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->admin) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$action = GETPOST('action', 'aZ09');

// GitHub config
$ghRepo = 'eighteen-ai/DOLIBARR-SMSHUB';
$ghToken = getDolGlobalString('SMSHUB_GITHUB_TOKEN', '');
$ghBranch = 'master';
$moduleDir = dol_buildpath('/smshub', 0);

$updateLog = '';

if ($action === 'save_token') {
	$token = GETPOST('github_token', 'none');
	dolibarr_set_const($db, "SMSHUB_GITHUB_TOKEN", $token, 'chaine', 0, '', $conf->entity);
	$ghToken = $token;
	setEventMessages('Token GitHub sauvegarde', null, 'mesgs');
}

if ($action === 'check_update' || $action === 'do_update') {
	if (empty($ghToken)) {
		setEventMessages('Token GitHub non configure', null, 'errors');
	} else {
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => "https://api.github.com/repos/{$ghRepo}/commits/{$ghBranch}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				'Authorization: token '.$ghToken,
				'User-Agent: SmsHub-Updater',
				'Accept: application/vnd.github.v3+json',
			),
			CURLOPT_TIMEOUT => 15,
		));
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200) {
			setEventMessages('Erreur GitHub (HTTP '.$httpCode.'): verifiez le token', null, 'errors');
		} else {
			$commitData = json_decode($response, true);
			$remoteCommit = $commitData['sha'] ?? '';
			$remoteDate = $commitData['commit']['committer']['date'] ?? '';
			$remoteMsg = $commitData['commit']['message'] ?? '';

			$localVersion = getDolGlobalString('SMSHUB_LAST_COMMIT', '');

			if ($action === 'check_update') {
				if ($localVersion === $remoteCommit) {
					setEventMessages('Module a jour (commit: '.substr($remoteCommit, 0, 8).')', null, 'mesgs');
				} else {
					setEventMessages('Mise a jour disponible ! Commit: '.substr($remoteCommit, 0, 8).' - '.$remoteMsg, null, 'warnings');
				}
			}

			if ($action === 'do_update') {
				$ch = curl_init();
				curl_setopt_array($ch, array(
					CURLOPT_URL => "https://api.github.com/repos/{$ghRepo}/zipball/{$ghBranch}",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTPHEADER => array(
						'Authorization: token '.$ghToken,
						'User-Agent: SmsHub-Updater',
						'Accept: application/vnd.github.v3+json',
					),
					CURLOPT_TIMEOUT => 60,
				));
				$zipContent = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($httpCode !== 200 || empty($zipContent)) {
					setEventMessages('Erreur telechargement ZIP (HTTP '.$httpCode.')', null, 'errors');
				} else {
					$tmpZip = tempnam(sys_get_temp_dir(), 'smshub_update_').'.zip';
					file_put_contents($tmpZip, $zipContent);

					$zip = new ZipArchive();
					if ($zip->open($tmpZip) === true) {
						$rootDir = '';
						for ($i = 0; $i < $zip->numFiles; $i++) {
							$name = $zip->getNameIndex($i);
							if (strpos($name, '/smshub/') !== false) {
								$rootDir = substr($name, 0, strpos($name, '/smshub/'));
								break;
							}
						}

						if (empty($rootDir)) {
							setEventMessages('Structure ZIP invalide: dossier smshub/ non trouve', null, 'errors');
						} else {
							$updateLog = '';
							$prefix = $rootDir.'/smshub/';
							$prefixLen = strlen($prefix);

							for ($i = 0; $i < $zip->numFiles; $i++) {
								$name = $zip->getNameIndex($i);
								if (strpos($name, $prefix) !== 0) continue;

								$relativePath = substr($name, $prefixLen);
								if (empty($relativePath)) continue;

								$destPath = $moduleDir.'/'.$relativePath;

								if (substr($name, -1) === '/') {
									if (!is_dir($destPath)) {
										@mkdir($destPath, 0755, true);
										$updateLog .= "DIR: {$relativePath}\n";
									}
									continue;
								}

								$dir = dirname($destPath);
								if (!is_dir($dir)) @mkdir($dir, 0755, true);

								$content = $zip->getFromIndex($i);
								if ($content !== false) {
									file_put_contents($destPath, $content);
									$updateLog .= "FILE: {$relativePath}\n";
								}
							}

							$zip->close();
							@unlink($tmpZip);

							dolibarr_set_const($db, "SMSHUB_LAST_COMMIT", $remoteCommit, 'chaine', 0, '', $conf->entity);
							dolibarr_set_const($db, "SMSHUB_LAST_UPDATE", dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S'), 'chaine', 0, '', $conf->entity);

							setEventMessages('Mise a jour effectuee avec succes ! Commit: '.substr($remoteCommit, 0, 8).' - '.$remoteMsg, null, 'mesgs');
						}
					} else {
						setEventMessages('Impossible d\'ouvrir le ZIP', null, 'errors');
						@unlink($tmpZip);
					}
				}
			}
		}
	}
}

$page_name = "SMSHUB - Mise a jour";
llxHeader('', $page_name);

print load_fiche_titre($page_name, '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'update', '', -1);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_token">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield" colspan="2">Configuration GitHub</td></tr>';

print '<tr class="oddeven"><td>Token GitHub</td><td>';
print '<input type="password" name="github_token" value="'.dol_escape_htmltag($ghToken).'" class="flat minwidth400">';
print ' <input type="submit" class="button" value="Sauvegarder">';
print '</td></tr>';

print '<tr class="oddeven"><td>Repository</td><td><strong>'.$ghRepo.'</strong> (branche '.$ghBranch.')</td></tr>';

$lastUpdate = getDolGlobalString('SMSHUB_LAST_UPDATE', 'Jamais');
$lastCommit = getDolGlobalString('SMSHUB_LAST_COMMIT', '');
print '<tr class="oddeven"><td>Derniere mise a jour</td><td>'.$lastUpdate.($lastCommit ? ' (commit: '.substr($lastCommit, 0, 8).')' : '').'</td></tr>';

print '</table>';
print '</form>';

print '<br>';
print '<div class="center">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=check_update&token='.newToken().'">Verifier les mises a jour</a>';
print ' &nbsp; ';
print '<a class="button button-save" href="'.$_SERVER["PHP_SELF"].'?action=do_update&token='.newToken().'" onclick="return confirm(\'Confirmer la mise a jour du module depuis GitHub ?\')">Mettre a jour maintenant</a>';
print '</div>';

if (!empty($updateLog)) {
	print '<br><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Journal de mise a jour</td></tr>';
	print '<tr class="oddeven"><td><pre style="font-size:12px;max-height:300px;overflow:auto">'.dol_escape_htmltag($updateLog).'</pre></td></tr>';
	print '</table>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();

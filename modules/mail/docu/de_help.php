<?php

$helplet['modul'] = 'Mail';
$helplet['action'] = 'Hilfe';
$helplet['info'] = 'Hier siehst du alle Nachrichten, die vom System, oder anderen Benutzern an dich gesendet wurden und können auch selbst anderen Benutzern eine Nachricht hinterlassen';

$helplet['key'][1] = 'Du hast Post!';
$helplet['value'][1] = 'Immer dann wenn neue Nachrichten für dich eintreffen, wirst du rechts in der Benutzerdaten-Box mit einem Brief-Icon darauf hingewiesen. Durch Anklicken dieses Icons gelangst du zu deiner neuen Nachricht';
$helplet['key'][2] = 'Rundmail';
$helplet['value'][2] = 'Über den gleichnamigen Link können Organisatoren eine Rundmail an alle, oder einen Teil der im System registrierten Benutzer rausschicken. Wir empfehlen die Option "Nur an Benutzer, die den Newsletter bei der Anmeldung bestellt haben" nur in dringenden Ausnahmefällen zu deaktivieren, da viele Benutzer die dieses Feld bei der Anmeldung deaktivieren keine Mails von dir empfangen möchten. Über verschiedene Variablen, lässt sich der Newsletter individualisieren.';
$helplet['key'][3] = 'Mails löschen';
$helplet['value'][3] = 'Du hast lediglich die Möglichkeit eine Mail aus dem Posteingang in den Papierkorb zu verschieben. Ein endgültiges Löschen einer Mail aus der Datenbank ist nicht möglich, denn das würde die Mail auch beim Sender löschen. Da dies zu Verwirrung führen kann und von diesem evtl. überhaupt nicht gewünscht ist können Mails daher nicht gelöscht werden';
$helplet['key'][4] = 'Labels';
$helplet['value'][4] = 'Aus dem gleichen Grund, wie beim Löschen, ist es nicht Möglich den Lese-Status wieder auf "Ungelesen" zu setzen. Statt dessen gibt es die Möglichekeit Mails im Posteingang mit 5 verschiedenfarbigen Labels zu markieren';
$helplet['key'][5] = 'Internet-Mails';
$helplet['value'][5] = 'Registrierung, Passwort-Link und Benachrichtigungen nach außen laufen über PHP mail() oder SMTP. Beides stellst du unter Installation, Modul Mail ein. Der Absender ist die Kontaktmail der Lanparty. Viele Hoster und der Docker-Container haben kein Sendmail, dann muss SMTP mit Host, Port und Verschlüsselung (STARTTLS, SMTPS oder keine) eingetragen werden. Das SMTP-Passwort wird nicht wieder angezeigt. Administratoren können unter Testmail prüfen, ob der Versand funktioniert.';

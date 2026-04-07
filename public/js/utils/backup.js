async function backupDB() {
    try {
        const fileName = 'database_backup_' + new Date().toISOString().replace(/[:.]/g, '-') + '.sqlite';
        const canUseFolderPicker =
            window.isSecureContext &&
            typeof window.showDirectoryPicker === 'function';

        if (!canUseFolderPicker) {
            window.location.href = '/backup-db?download=1';
            if (typeof showMessageBox === 'function') {
                showMessageBox('info', 'Backup download started. Browser save location settings apply on this URL.');
            }
            return;
        }

        const response = await fetch('/backup-db');

        if (!response.ok) {
            let serverMessage = `Backup request failed (${response.status})`;

            try {
                serverMessage = await response.text();
                serverMessage = serverMessage.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || serverMessage;
            } catch (_) {}

            throw new Error(serverMessage);
        }

        const blob = await response.blob();

        if (blob.size < 10240) {
            throw new Error('Backup file looks too small. Please try again.');
        }

        if (canUseFolderPicker) {
            const handle = await window.showDirectoryPicker();
            const fileHandle = await handle.getFileHandle(fileName, { create: true });
            const writable = await fileHandle.createWritable();
            await writable.write(blob);
            await writable.close();

            if (typeof showMessageBox === 'function') {
                showMessageBox('success', 'Backup saved to selected folder.');
            }
            return;
        }
    } catch (err) {
        console.error(err);
        const errorMessage = (err && err.message ? err.message : 'Backup failed or cancelled.').replace(/'/g, "\\'");

        if (typeof showMessageBox === 'function') {
            showMessageBox('error', errorMessage);
        }
    }
}

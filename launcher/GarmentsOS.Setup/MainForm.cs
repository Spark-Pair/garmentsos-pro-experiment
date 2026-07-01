using System.Diagnostics;
using System.IO.Compression;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace GarmentsOS.Setup;

public sealed class MainForm : Form
{
    private const string DefaultInstallDir = @"C:\SparkPair\GarmentsOS";
    private const string AppUrl = "http://localhost:8000";

    private readonly HttpClient http = new() { Timeout = TimeSpan.FromSeconds(30) };
    private readonly JsonSerializerOptions jsonOptions = new() { PropertyNameCaseInsensitive = true };

    private readonly TextBox installDirBox = new() { Text = DefaultInstallDir };
    private readonly TextBox feedUrlBox = new();
    private readonly Label titleLabel = new()
    {
        Text = "GarmentsOS PRO Updater / Setup Launcher",
        Font = new Font(SystemFonts.DefaultFont.FontFamily, 14, FontStyle.Bold),
        AutoSize = true,
    };
    private readonly Label installedVersionLabel = new() { Text = "Installed version: unknown", AutoSize = true };
    private readonly Label appStatusLabel = new() { Text = "App status: unknown", AutoSize = true };
    private readonly Label dockerStatusLabel = new() { Text = "Docker status: unknown", AutoSize = true };
    private readonly Label latestVersionLabel = new() { Text = "Latest version: not checked", AutoSize = true };
    private readonly Label mandatoryLabel = new() { Text = "Mandatory: false", AutoSize = true };
    private readonly Label progressStatusLabel = new() { Text = "Preparing update", AutoSize = true };
    private readonly ProgressBar progressBar = new() { Style = ProgressBarStyle.Marquee, MarqueeAnimationSpeed = 35, Dock = DockStyle.Top };
    private readonly TextBox notesBox = new() { Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical };
    private readonly TextBox logBox = new() { Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical };
    private readonly Button updateButton = new() { Text = "Update Now", Enabled = false };
    private readonly Button detailsButton = new() { Text = "Details", AutoSize = true };
    private readonly FlowLayoutPanel buttonsPanel = new() { Dock = DockStyle.Top, AutoSize = true, WrapContents = true };
    private readonly FlowLayoutPanel failureButtonsPanel = new() { Dock = DockStyle.Top, AutoSize = true, WrapContents = true, Visible = false };
    private readonly TableLayoutPanel pathsPanel = new() { Dock = DockStyle.Top, ColumnCount = 2, AutoSize = true };
    private readonly TableLayoutPanel statusPanel = new() { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 4 };
    private readonly Panel progressPanel = new() { Dock = DockStyle.Top, Visible = false, Padding = new Padding(0, 10, 0, 10) };

    private readonly string? startupArgument;
    private ReleaseFeed? currentFeed;
    private bool autoUpdateMode;
    private bool criticalUpdateStep;
    private bool detailsExpanded;

    public MainForm(string? startupArgument = null)
    {
        this.startupArgument = startupArgument;
        Text = "GarmentsOS PRO Updater";
        Width = 900;
        Height = 680;
        MinimumSize = new Size(760, 560);
        StartPosition = FormStartPosition.CenterScreen;

        Controls.Add(BuildLayout());

        Load += async (_, _) =>
        {
            await RefreshStatusAsync();
            await HandleStartupArgumentAsync();
        };
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (criticalUpdateStep)
        {
            e.Cancel = true;
            Log("Update is in progress. Please wait until the current step finishes.");
            return;
        }

        base.OnFormClosing(e);
    }

    private Control BuildLayout()
    {
        var root = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 7,
            Padding = new Padding(14),
        };
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 35));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 65));

        root.Controls.Add(titleLabel);

        pathsPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 120));
        pathsPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        pathsPanel.Controls.Add(new Label { Text = "Install folder", AutoSize = true, Anchor = AnchorStyles.Left }, 0, 0);
        pathsPanel.Controls.Add(installDirBox, 1, 0);
        pathsPanel.Controls.Add(new Label { Text = "Update feed URL", AutoSize = true, Anchor = AnchorStyles.Left }, 0, 1);
        pathsPanel.Controls.Add(feedUrlBox, 1, 1);
        root.Controls.Add(pathsPanel);

        progressPanel.Controls.Add(BuildProgressPanel());
        root.Controls.Add(progressPanel);

        statusPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        statusPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        statusPanel.Controls.Add(installedVersionLabel, 0, 0);
        statusPanel.Controls.Add(appStatusLabel, 0, 1);
        statusPanel.Controls.Add(dockerStatusLabel, 0, 2);
        statusPanel.Controls.Add(latestVersionLabel, 1, 0);
        statusPanel.Controls.Add(mandatoryLabel, 1, 1);
        notesBox.Dock = DockStyle.Fill;
        statusPanel.Controls.Add(notesBox, 0, 3);
        statusPanel.SetColumnSpan(notesBox, 2);
        root.Controls.Add(statusPanel);

        AddButton(buttonsPanel, "Open App", async (_, _) => await OpenAppAsync());
        AddButton(buttonsPanel, "Check Update", async (_, _) => await CheckUpdateAsync());
        updateButton.Click += async (_, _) => await UpdateNowAsync(requireConfirmation: true, closeAfterSuccess: false);
        buttonsPanel.Controls.Add(updateButton);
        AddButton(buttonsPanel, "Open Request JSON", async (_, _) => await OpenRequestJsonAsync());
        AddButton(buttonsPanel, "Backup", async (_, _) => await RunInstalledScriptAsync("scripts\\windows-docker-backup.ps1"));
        AddButton(buttonsPanel, "Repair", async (_, _) => await RunPowerShellAsync("docker compose up -d", installDirBox.Text));
        AddButton(buttonsPanel, "Stop Services", async (_, _) => await StopServicesAsync());
        AddButton(buttonsPanel, "Open Install Folder", (_, _) => OpenFolder(installDirBox.Text));
        AddButton(buttonsPanel, "Close", (_, _) => Close());
        root.Controls.Add(buttonsPanel);

        AddButton(failureButtonsPanel, "Open Install Folder", (_, _) => OpenFolder(installDirBox.Text));
        AddButton(failureButtonsPanel, "Save Log", (_, _) => SaveLog());
        AddButton(failureButtonsPanel, "Close", (_, _) => Close());
        root.Controls.Add(failureButtonsPanel);

        logBox.Dock = DockStyle.Fill;
        root.Controls.Add(logBox);

        return root;
    }

    private Control BuildProgressPanel()
    {
        var panel = new TableLayoutPanel { Dock = DockStyle.Top, ColumnCount = 1, AutoSize = true };
        panel.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        panel.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        panel.RowStyles.Add(new RowStyle(SizeType.AutoSize));

        var heading = new Label
        {
            Text = "Updating GarmentsOS PRO",
            Font = new Font(Font.FontFamily, 16, FontStyle.Bold),
            AutoSize = true,
            Margin = new Padding(0, 0, 0, 8),
        };
        panel.Controls.Add(heading);

        progressStatusLabel.Margin = new Padding(0, 0, 0, 8);
        panel.Controls.Add(progressStatusLabel);

        progressBar.Height = 22;
        progressBar.Margin = new Padding(0, 0, 0, 8);
        panel.Controls.Add(progressBar);

        detailsButton.Click += (_, _) => ToggleDetails();
        panel.Controls.Add(detailsButton);

        return panel;
    }

    private static void AddButton(Control parent, string text, EventHandler handler)
    {
        var button = new Button { Text = text, AutoSize = true, Margin = new Padding(4) };
        button.Click += handler;
        parent.Controls.Add(button);
    }

    private void EnterAutoUpdateMode()
    {
        autoUpdateMode = true;
        criticalUpdateStep = false;
        detailsExpanded = false;
        Text = "GarmentsOS PRO Updating";
        titleLabel.Text = "GarmentsOS PRO Updating";
        pathsPanel.Visible = false;
        statusPanel.Visible = false;
        buttonsPanel.Visible = false;
        progressPanel.Visible = true;
        failureButtonsPanel.Visible = false;
        logBox.Visible = false;
        ControlBox = false;
        SetStep("Preparing update", marquee: true);
    }

    private void ToggleDetails()
    {
        detailsExpanded = !detailsExpanded;
        logBox.Visible = detailsExpanded || failureButtonsPanel.Visible;
        detailsButton.Text = detailsExpanded ? "Hide Details" : "Details";
    }

    private void SetStep(string message, int? percent = null, bool marquee = false)
    {
        if (InvokeRequired)
        {
            BeginInvoke(() => SetStep(message, percent, marquee));
            return;
        }

        progressStatusLabel.Text = message;
        if (percent.HasValue)
        {
            progressBar.Style = ProgressBarStyle.Continuous;
            progressBar.MarqueeAnimationSpeed = 0;
            progressBar.Value = Math.Clamp(percent.Value, progressBar.Minimum, progressBar.Maximum);
        }
        else if (marquee)
        {
            progressBar.Style = ProgressBarStyle.Marquee;
            progressBar.MarqueeAnimationSpeed = 35;
        }
    }

    private void ShowFailureMode()
    {
        criticalUpdateStep = false;
        ControlBox = true;
        SetStep("Update failed", percent: 0);
        failureButtonsPanel.Visible = true;
        logBox.Visible = true;
        detailsExpanded = true;
        detailsButton.Text = "Hide Details";
    }

    private async Task RefreshStatusAsync()
    {
        var installDir = installDirBox.Text.Trim();
        var manifest = ReadInstalledManifest(installDir);
        installedVersionLabel.Text = $"Installed version: {manifest?.Version ?? "not found"}";
        feedUrlBox.Text = ReadEnvValue(Path.Combine(installDir, ".env"), "UPDATE_FEED_URL")
            ?? ReadEnvValue(Path.Combine(installDir, ".env"), "UPDATER_MANIFEST_URL")
            ?? "";

        dockerStatusLabel.Text = "Docker status: checking...";
        appStatusLabel.Text = "App status: checking...";
        dockerStatusLabel.Text = $"Docker status: {await GetDockerStatusAsync()}";
        appStatusLabel.Text = $"App status: {await GetAppStatusAsync()}";
    }

    private async Task CheckUpdateAsync()
    {
        try
        {
            await RefreshStatusAsync();
            var feedUrl = feedUrlBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(feedUrl))
            {
                Log("Update feed URL is not configured.");
                return;
            }

            currentFeed = await FetchFeedAsync(feedUrl);
            latestVersionLabel.Text = $"Latest version: {currentFeed.Version}";
            mandatoryLabel.Text = $"Mandatory: {currentFeed.Mandatory}";
            notesBox.Text = currentFeed.Notes;

            var installed = ReadInstalledManifest(installDirBox.Text.Trim())?.Version ?? "0.0.0";
            updateButton.Enabled = VersionCompare(currentFeed.Version, installed) > 0;
            Log(updateButton.Enabled ? $"Update available: {installed} -> {currentFeed.Version}" : "Installed app is up to date.");
        }
        catch (Exception ex)
        {
            currentFeed = null;
            updateButton.Enabled = false;
            Log("Update check failed: " + ex.Message);
        }
    }

    private async Task OpenRequestJsonAsync()
    {
        using var dialog = new OpenFileDialog
        {
            Title = "Open GarmentsOS update request",
            Filter = "GarmentsOS update request (*.json)|*.json|All files (*.*)|*.*",
        };

        if (dialog.ShowDialog(this) != DialogResult.OK)
        {
            return;
        }

        await LoadRequestJsonFromFileAsync(dialog.FileName);
    }

    private async Task HandleStartupArgumentAsync()
    {
        if (string.IsNullOrWhiteSpace(startupArgument))
        {
            return;
        }

        if (!Uri.TryCreate(startupArgument, UriKind.Absolute, out var uri) ||
            !string.Equals(uri.Scheme, "garmentsos", StringComparison.OrdinalIgnoreCase))
        {
            Log("Ignoring unsupported startup argument.");
            return;
        }

        var action = string.IsNullOrWhiteSpace(uri.Host)
            ? uri.AbsolutePath.Trim('/')
            : uri.Host;

        if (string.Equals(action, "open", StringComparison.OrdinalIgnoreCase))
        {
            Log("Launcher opened from garmentsos://open.");
            await OpenAppAsync();
            return;
        }

        if (!string.Equals(action, "update", StringComparison.OrdinalIgnoreCase))
        {
            Log("Unsupported GarmentsOS protocol action: " + action);
            return;
        }

        var request = QueryValue(uri.Query, "request");
        var autoStart = string.Equals(QueryValue(uri.Query, "autoStart"), "1", StringComparison.OrdinalIgnoreCase)
            || string.Equals(QueryValue(uri.Query, "autostart"), "true", StringComparison.OrdinalIgnoreCase);

        if (autoStart)
        {
            EnterAutoUpdateMode();
            Log("Automatic update mode started from garmentsos://update.");
        }
        else
        {
            Log("Launcher opened from garmentsos://update.");
        }

        if (string.IsNullOrWhiteSpace(request))
        {
            if (autoStart)
            {
                ShowFailureMode();
                Log("Update request was missing from protocol URL.");
            }
            return;
        }

        await LoadRequestJsonReferenceAsync(request);

        if (autoStart && currentFeed is not null)
        {
            await UpdateNowAsync(requireConfirmation: false, closeAfterSuccess: true);
        }
    }

    private async Task LoadRequestJsonReferenceAsync(string reference)
    {
        var decoded = Uri.UnescapeDataString(reference.Trim());

        try
        {
            if (Uri.TryCreate(decoded, UriKind.Absolute, out var uri))
            {
                if (uri.Scheme.Equals("http", StringComparison.OrdinalIgnoreCase) ||
                    uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                {
                    SetStep("Preparing update", marquee: true);
                    Log("Loading update request JSON from URL...");
                    using var response = await http.GetAsync(uri);
                    response.EnsureSuccessStatusCode();
                    await LoadRequestJsonAsync(await response.Content.ReadAsStringAsync());
                    return;
                }

                if (uri.Scheme.Equals("file", StringComparison.OrdinalIgnoreCase))
                {
                    await LoadRequestJsonFromFileAsync(uri.LocalPath);
                    return;
                }
            }

            if (File.Exists(decoded))
            {
                await LoadRequestJsonFromFileAsync(decoded);
                return;
            }

            Log("Update request reference was not a supported URL or existing local file.");
            if (autoUpdateMode)
            {
                ShowFailureMode();
            }
        }
        catch (Exception ex)
        {
            Log("Could not load protocol update request: " + ex.Message);
            if (autoUpdateMode)
            {
                ShowFailureMode();
            }
        }
    }

    private async Task LoadRequestJsonFromFileAsync(string path)
    {
        await LoadRequestJsonAsync(await File.ReadAllTextAsync(path));
        Log("Loaded update request JSON: " + path);
    }

    private Task LoadRequestJsonAsync(string json)
    {
        var request = JsonSerializer.Deserialize<UpdateRequest>(json, jsonOptions)
            ?? throw new InvalidOperationException("Update request JSON could not be read.");

        if (string.IsNullOrWhiteSpace(request.TargetVersion) ||
            string.IsNullOrWhiteSpace(request.PackageUrl) ||
            string.IsNullOrWhiteSpace(request.PackageSha256))
        {
            throw new InvalidOperationException("Update request is missing target_version, package_url, or package_sha256.");
        }

        currentFeed = new ReleaseFeed
        {
            Version = request.TargetVersion,
            Channel = request.Channel,
            PackageFile = request.PackageFile,
            PackageUrl = request.PackageUrl,
            PackageSha256 = request.PackageSha256,
            Mandatory = request.Mandatory,
            Notes = request.Notes,
        };

        latestVersionLabel.Text = $"Latest version: {currentFeed.Version}";
        mandatoryLabel.Text = $"Mandatory: {currentFeed.Mandatory}";
        notesBox.Text = currentFeed.Notes;
        updateButton.Enabled = !string.IsNullOrWhiteSpace(currentFeed.PackageUrl)
            && !string.IsNullOrWhiteSpace(currentFeed.PackageSha256);

        Log(autoUpdateMode
            ? $"Update request loaded. Starting update to {currentFeed.Version}."
            : "Update request is ready. Review details, then click Update Now.");

        return Task.CompletedTask;
    }

    private async Task UpdateNowAsync(bool requireConfirmation, bool closeAfterSuccess)
    {
        if (currentFeed is null)
        {
            Log("No update feed/request is loaded.");
            return;
        }

        if (string.IsNullOrWhiteSpace(currentFeed.PackageUrl) || string.IsNullOrWhiteSpace(currentFeed.PackageSha256))
        {
            Log("Update package URL or SHA256 is missing.");
            return;
        }

        if (requireConfirmation)
        {
            var confirm = MessageBox.Show(
                "The Windows updater will apply this update outside the running app. Docker volumes, data, and backups are preserved by the update script.",
                "Apply GarmentsOS PRO Update",
                MessageBoxButtons.OKCancel,
                MessageBoxIcon.Warning);

            if (confirm != DialogResult.OK)
            {
                return;
            }
        }

        updateButton.Enabled = false;
        criticalUpdateStep = true;
        if (autoUpdateMode)
        {
            ControlBox = false;
        }

        try
        {
            var workDir = Path.Combine(Path.GetTempPath(), "GarmentsOSUpdate", DateTime.Now.ToString("yyyyMMdd_HHmmss"));
            Directory.CreateDirectory(workDir);

            var packagePath = Path.Combine(workDir, Path.GetFileName(new Uri(currentFeed.PackageUrl).LocalPath));
            SetStep("Downloading update package", marquee: true);
            Log("Downloading update package...");
            await DownloadFileAsync(currentFeed.PackageUrl, packagePath);

            SetStep("Verifying package", marquee: true);
            Log("Verifying package SHA256...");
            var actualSha = await ComputeSha256Async(packagePath);
            if (!actualSha.Equals(currentFeed.PackageSha256, StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException($"SHA256 mismatch. Expected {currentFeed.PackageSha256}, got {actualSha}.");
            }

            SetStep("Preparing update", marquee: true);
            var extractDir = Path.Combine(workDir, "extracted");
            Directory.CreateDirectory(extractDir);
            ExtractPackage(packagePath, extractDir);
            var releaseDir = FindReleaseDir(extractDir);

            Log("Running Windows Docker updater...");
            var script = Path.Combine(releaseDir, "scripts", "windows-docker-update.ps1");
            if (!File.Exists(script))
            {
                throw new FileNotFoundException("Update script not found in package.", script);
            }

            SetStep("Creating backup", marquee: true);
            await RunProcessAsync(
                "powershell.exe",
                $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -InstallDir \"{installDirBox.Text.Trim()}\" -ReleaseDir \"{releaseDir}\"",
                releaseDir,
                line =>
                {
                    if (line.Contains("backup", StringComparison.OrdinalIgnoreCase))
                    {
                        SetStep("Creating backup", marquee: true);
                    }
                    else if (line.Contains("docker load", StringComparison.OrdinalIgnoreCase) || line.Contains("Loaded image", StringComparison.OrdinalIgnoreCase))
                    {
                        SetStep("Applying update", marquee: true);
                    }
                    else if (line.Contains("compose", StringComparison.OrdinalIgnoreCase) || line.Contains("started", StringComparison.OrdinalIgnoreCase))
                    {
                        SetStep("Restarting services", marquee: true);
                    }
                });

            SetStep("Opening app", percent: 95);
            StartPendingLauncherReplacementHelper(installDirBox.Text.Trim());
            Log("Update complete. Opening app...");
            OpenUrl(AppUrl);
            criticalUpdateStep = false;
            ControlBox = true;
            SetStep("Update complete", percent: 100);
            await RefreshStatusAsync();

            if (closeAfterSuccess && !detailsExpanded)
            {
                var timer = new System.Windows.Forms.Timer { Interval = 4000 };
                timer.Tick += (_, _) =>
                {
                    timer.Stop();
                    Close();
                };
                timer.Start();
            }
        }
        catch (Exception ex)
        {
            Log("Update failed: " + ex.Message);
            updateButton.Enabled = true;
            if (autoUpdateMode)
            {
                ShowFailureMode();
            }
            else
            {
                criticalUpdateStep = false;
            }
        }
    }

    private async Task<ReleaseFeed> FetchFeedAsync(string feedUrl)
    {
        using var response = await http.GetAsync(feedUrl);
        response.EnsureSuccessStatusCode();
        var feed = JsonSerializer.Deserialize<ReleaseFeed>(await response.Content.ReadAsStringAsync(), jsonOptions)
            ?? throw new InvalidOperationException("Update feed JSON could not be read.");

        if (!string.Equals(feed.App, "garmentsos-pro", StringComparison.OrdinalIgnoreCase) && !string.IsNullOrWhiteSpace(feed.App))
        {
            throw new InvalidOperationException("Update feed is for a different app.");
        }

        if (string.IsNullOrWhiteSpace(feed.Version) || string.IsNullOrWhiteSpace(feed.PackageUrl) || string.IsNullOrWhiteSpace(feed.PackageSha256))
        {
            throw new InvalidOperationException("Update feed is missing version, package_url, or package_sha256.");
        }

        return feed;
    }

    private async Task DownloadFileAsync(string url, string destination)
    {
        await using var source = await http.GetStreamAsync(url);
        await using var target = File.Create(destination);
        await source.CopyToAsync(target);
    }

    private static async Task<string> ComputeSha256Async(string path)
    {
        await using var stream = File.OpenRead(path);
        var hash = await SHA256.HashDataAsync(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private static void ExtractPackage(string packagePath, string extractDir)
    {
        if (packagePath.EndsWith(".zip", StringComparison.OrdinalIgnoreCase))
        {
            ZipFile.ExtractToDirectory(packagePath, extractDir, true);
            return;
        }

        if (packagePath.EndsWith(".tar.gz", StringComparison.OrdinalIgnoreCase) || packagePath.EndsWith(".tgz", StringComparison.OrdinalIgnoreCase))
        {
            var result = Process.Start(new ProcessStartInfo
            {
                FileName = "tar.exe",
                Arguments = $"-xzf \"{packagePath}\" -C \"{extractDir}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            });
            result?.WaitForExit();
            if (result is null || result.ExitCode != 0)
            {
                throw new InvalidOperationException("tar.exe could not extract the update package.");
            }
            return;
        }

        throw new InvalidOperationException("Unsupported update package format. Use .zip or .tar.gz.");
    }

    private static string FindReleaseDir(string extractDir)
    {
        if (File.Exists(Path.Combine(extractDir, "manifest.json")))
        {
            return extractDir;
        }

        var found = Directory.GetDirectories(extractDir)
            .FirstOrDefault(path => File.Exists(Path.Combine(path, "manifest.json")));

        return found ?? throw new DirectoryNotFoundException("Extracted package does not contain manifest.json.");
    }

    private InstalledManifest? ReadInstalledManifest(string installDir)
    {
        var path = Path.Combine(installDir, "manifest.json");
        if (!File.Exists(path))
        {
            return null;
        }

        try
        {
            return JsonSerializer.Deserialize<InstalledManifest>(File.ReadAllText(path), jsonOptions);
        }
        catch
        {
            return null;
        }
    }

    private static string? ReadEnvValue(string envPath, string key)
    {
        if (!File.Exists(envPath))
        {
            return null;
        }

        foreach (var line in File.ReadLines(envPath))
        {
            var trimmed = line.Trim();
            if (trimmed.StartsWith("#") || !trimmed.StartsWith(key + "=", StringComparison.Ordinal))
            {
                continue;
            }

            return trimmed[(key.Length + 1)..].Trim().Trim('"', '\'');
        }

        return null;
    }

    private async Task<string> GetDockerStatusAsync()
    {
        try
        {
            var result = await RunProcessCaptureAsync("docker", "info", installDirBox.Text.Trim());
            return result == 0 ? "running" : "not available";
        }
        catch
        {
            return "not available";
        }
    }

    private async Task<string> GetAppStatusAsync()
    {
        try
        {
            using var response = await http.GetAsync(AppUrl);
            return response.IsSuccessStatusCode || ((int) response.StatusCode >= 300 && (int) response.StatusCode < 500)
                ? "reachable"
                : "not reachable";
        }
        catch
        {
            return "not reachable";
        }
    }

    private async Task RunInstalledScriptAsync(string relativeScript)
    {
        var script = Path.Combine(installDirBox.Text.Trim(), relativeScript);
        if (!File.Exists(script))
        {
            Log("Script not found: " + script);
            return;
        }

        await RunProcessAsync("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -InstallDir \"{installDirBox.Text.Trim()}\"", installDirBox.Text.Trim());
    }

    private async Task RunPowerShellAsync(string command, string workingDirectory)
    {
        await RunProcessAsync("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -Command \"{command}\"", workingDirectory);
    }

    private async Task StopServicesAsync()
    {
        var stopBat = Path.Combine(installDirBox.Text.Trim(), "Stop GarmentsOS.bat");
        if (File.Exists(stopBat))
        {
            await RunProcessAsync("cmd.exe", $"/c \"{stopBat}\"", installDirBox.Text.Trim());
            return;
        }

        await RunPowerShellAsync("docker compose down", installDirBox.Text.Trim());
    }

    private async Task OpenAppAsync()
    {
        try
        {
            await RunPowerShellAsync("docker compose up -d", installDirBox.Text.Trim());
        }
        catch (Exception ex)
        {
            Log("Could not start services before opening app: " + ex.Message);
        }

        OpenUrl(AppUrl);
    }

    private async Task<int> RunProcessCaptureAsync(string fileName, string arguments, string workingDirectory)
    {
        var process = StartProcess(fileName, arguments, workingDirectory);
        _ = process.StandardOutput.ReadToEndAsync();
        _ = process.StandardError.ReadToEndAsync();
        await process.WaitForExitAsync();
        return process.ExitCode;
    }

    private async Task RunProcessAsync(string fileName, string arguments, string workingDirectory, Action<string>? onOutput = null)
    {
        var process = StartProcess(fileName, arguments, workingDirectory);
        process.OutputDataReceived += (_, e) =>
        {
            if (e.Data is not null)
            {
                Log(e.Data);
                onOutput?.Invoke(e.Data);
            }
        };
        process.ErrorDataReceived += (_, e) =>
        {
            if (e.Data is not null)
            {
                Log(e.Data);
                onOutput?.Invoke(e.Data);
            }
        };
        process.BeginOutputReadLine();
        process.BeginErrorReadLine();
        await process.WaitForExitAsync();
        if (process.ExitCode != 0)
        {
            throw new InvalidOperationException($"{fileName} exited with code {process.ExitCode}.");
        }
    }

    private static Process StartProcess(string fileName, string arguments, string workingDirectory)
    {
        var process = new Process
        {
            StartInfo = new ProcessStartInfo
            {
                FileName = fileName,
                Arguments = arguments,
                WorkingDirectory = Directory.Exists(workingDirectory)
                    ? workingDirectory
                    : (Directory.Exists(DefaultInstallDir) ? DefaultInstallDir : Environment.CurrentDirectory),
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
            },
        };
        process.Start();
        return process;
    }

    private void StartPendingLauncherReplacementHelper(string installDir)
    {
        var markerPath = Path.Combine(installDir, ".pending-launcher-update.json");
        if (!File.Exists(markerPath))
        {
            return;
        }

        try
        {
            var helperPath = Path.Combine(Path.GetTempPath(), "GarmentsOSPendingLauncherUpdate.ps1");
            var script = """
                param(
                    [int]$ParentPid,
                    [string]$MarkerPath
                )

                $ErrorActionPreference = "Stop"

                try {
                    Wait-Process -Id $ParentPid -ErrorAction SilentlyContinue
                    Start-Sleep -Seconds 1

                    if (-not (Test-Path -LiteralPath $MarkerPath)) {
                        exit 0
                    }

                    $marker = Get-Content -LiteralPath $MarkerPath -Raw | ConvertFrom-Json
                    Copy-Item -LiteralPath $marker.pending_path -Destination $marker.destination_path -Force
                    Remove-Item -LiteralPath $marker.pending_path -Force -ErrorAction SilentlyContinue
                    Remove-Item -LiteralPath $MarkerPath -Force -ErrorAction SilentlyContinue

                    $launcher = $marker.destination_path
                    $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
                    $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
                    $baseKey.SetValue("URL Protocol", "")
                    $baseKey.Close()

                    $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
                    $commandKey.SetValue("", "`"$launcher`" `"%1`"")
                    $commandKey.Close()
                } catch {
                    $logPath = Join-Path (Split-Path -Parent $MarkerPath) "pending-launcher-update.log"
                    "[$(Get-Date -Format o)] $($_.Exception.Message)" | Add-Content -Path $logPath
                }
                """;
            File.WriteAllText(helperPath, script, Encoding.UTF8);
            Process.Start(new ProcessStartInfo
            {
                FileName = "powershell.exe",
                Arguments = $"-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"{helperPath}\" -ParentPid {Environment.ProcessId} -MarkerPath \"{markerPath}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            });
            Log("Pending launcher update helper started.");
        }
        catch (Exception ex)
        {
            Log("Could not start pending launcher replacement helper: " + ex.Message);
        }
    }

    private void SaveLog()
    {
        using var dialog = new SaveFileDialog
        {
            Title = "Save GarmentsOS update log",
            Filter = "Text files (*.txt)|*.txt|All files (*.*)|*.*",
            FileName = "garmentsos-update-log.txt",
        };

        if (dialog.ShowDialog(this) == DialogResult.OK)
        {
            File.WriteAllText(dialog.FileName, logBox.Text);
        }
    }

    private static int VersionCompare(string left, string right)
    {
        return Version.TryParse(left, out var l) && Version.TryParse(right, out var r)
            ? l.CompareTo(r)
            : string.Compare(left, right, StringComparison.OrdinalIgnoreCase);
    }

    private static string? QueryValue(string query, string key)
    {
        var trimmed = query.TrimStart('?');
        if (string.IsNullOrWhiteSpace(trimmed))
        {
            return null;
        }

        foreach (var pair in trimmed.Split('&', StringSplitOptions.RemoveEmptyEntries))
        {
            var parts = pair.Split('=', 2);
            if (parts.Length == 2 && string.Equals(Uri.UnescapeDataString(parts[0]), key, StringComparison.OrdinalIgnoreCase))
            {
                return Uri.UnescapeDataString(parts[1]);
            }
        }

        return null;
    }

    private void Log(string message)
    {
        if (InvokeRequired)
        {
            BeginInvoke(() => Log(message));
            return;
        }

        logBox.AppendText($"[{DateTime.Now:HH:mm:ss}] {message}{Environment.NewLine}");
    }

    private static void OpenUrl(string url)
    {
        Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
    }

    private static void OpenFolder(string path)
    {
        Directory.CreateDirectory(path);
        Process.Start(new ProcessStartInfo(path) { UseShellExecute = true });
    }
}

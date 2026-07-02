using System.Diagnostics;
using System.Drawing.Drawing2D;
using System.IO.Compression;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace GarmentsOS.Setup;

public sealed class MainForm : Form
{
    private const string DefaultInstallDir = @"C:\SparkPair\GarmentsOS";
    private const string AppUrl = "http://localhost:8000";

    private static readonly Color BrandBlue = Color.FromArgb(37, 99, 235);
    private static readonly Color AppBackground = Color.FromArgb(238, 241, 244);
    private static readonly Color CardBorder = Color.FromArgb(185, 197, 212);
    private static readonly Color SoftBorder = Color.FromArgb(199, 208, 220);
    private static readonly Color TextPrimary = Color.FromArgb(15, 23, 42);
    private static readonly Color TextMuted = Color.FromArgb(75, 91, 112);
    private static readonly Color TextHint = Color.FromArgb(122, 135, 150);
    private static readonly Color SurfaceSoft = Color.FromArgb(248, 250, 252);


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
    private readonly Label progressStatusLabel = new() { Text = "Checking update package...", AutoSize = true };
    private readonly Label progressPercentLabel = new() { Text = "68%", AutoSize = true };
    private readonly Label installedFooterLabel = new() { Text = "Installed version: checking...", AutoSize = true };
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
    private readonly UpdaterSplashView splashView = new();

    private readonly string? startupArgument;
    private ReleaseFeed? currentFeed;
    private bool autoUpdateMode;
    private bool criticalUpdateStep;
    private bool detailsExpanded;

    public MainForm(string? startupArgument = null)
    {
        this.startupArgument = startupArgument;
        Text = "GarmentsOS PRO Updater";
        ClientSize = new Size(720, 420);
        MinimumSize = new Size(720, 420);
        MaximumSize = new Size(720, 420);
        StartPosition = FormStartPosition.CenterScreen;
        BackColor = AppBackground;
        Font = new Font("Segoe UI", 9f);
        FormBorderStyle = FormBorderStyle.None;
        MaximizeBox = false;
        DoubleBuffered = true;
        KeyPreview = true;

        Controls.Add(BuildLayout());
        ContextMenuStrip = BuildContextMenu();
        MouseDown += (_, e) => BeginDrag(e);
        KeyDown += (_, e) =>
        {
            if (e.KeyCode == Keys.Escape && !criticalUpdateStep)
            {
                Close();
            }
        };
        Resize += (_, _) => SetRoundedRegion();
        Shown += (_, _) => SetRoundedRegion();

        Load += async (_, _) =>
        {
            if (await HandlePendingLauncherUpdateOnStartupAsync())
            {
                return;
            }

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

    private ContextMenuStrip BuildContextMenu()
    {
        var menu = new ContextMenuStrip();
        menu.Items.Add("Open App", null, async (_, _) => await OpenAppAsync());
        menu.Items.Add("Check Update", null, async (_, _) => await CheckUpdateAsync());
        menu.Items.Add("Update Now", null, async (_, _) => await UpdateNowAsync(requireConfirmation: true, closeAfterSuccess: false));
        menu.Items.Add("Open Request JSON", null, async (_, _) => await OpenRequestJsonAsync());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Backup", null, async (_, _) => await RunInstalledScriptAsync("scripts\\windows-docker-backup.ps1"));
        menu.Items.Add("Repair", null, async (_, _) => await RunPowerShellAsync("docker compose up -d", installDirBox.Text));
        menu.Items.Add("Stop Services", null, async (_, _) => await StopServicesAsync());
        menu.Items.Add("Open Install Folder", null, (_, _) => OpenFolder(installDirBox.Text));
        menu.Items.Add("Save Log", null, (_, _) => SaveLog());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Close", null, (_, _) => Close());
        return menu;
    }

    private Control BuildLayout()
    {
        var root = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = AppBackground,
            Padding = new Padding(0),
        };

        splashView.Dock = DockStyle.Fill;
        splashView.LogoImage = TryLoadLogoBitmap(24);
        splashView.ProgressText = "Checking update package...";
        splashView.ProgressPercent = 68;
        splashView.InstalledVersionText = "Installed version: checking...";
        splashView.MouseDown += (_, e) => BeginDrag(e);
        root.Controls.Add(splashView);

        // Hidden fields keep the existing update/feed logic intact without cluttering the UI.
        installDirBox.Visible = false;
        feedUrlBox.Visible = false;
        pathsPanel.Visible = false;
        pathsPanel.Controls.Add(installDirBox);
        pathsPanel.Controls.Add(feedUrlBox);
        root.Controls.Add(pathsPanel);

        logBox.Visible = false;
        logBox.Multiline = true;
        logBox.ReadOnly = true;
        logBox.ScrollBars = ScrollBars.Vertical;
        logBox.Width = 640;
        logBox.Height = 130;
        logBox.Left = 44;
        logBox.Top = 222;
        logBox.Anchor = AnchorStyles.Left | AnchorStyles.Right | AnchorStyles.Bottom;
        root.Controls.Add(logBox);

        ConfigureActionButtons();
        failureButtonsPanel.Left = 44;
        failureButtonsPanel.Top = 358;
        failureButtonsPanel.BackColor = Color.Transparent;
        root.Controls.Add(failureButtonsPanel);

        return root;
    }

    private Control BuildSideStrip()
    {
        var side = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(245, 247, 251),
            Padding = new Padding(0, 28, 0, 28),
        };
        side.Paint += (_, e) =>
        {
            using var pen = new Pen(Color.FromArgb(229, 233, 240));
            e.Graphics.DrawLine(pen, side.Width - 1, 0, side.Width - 1, side.Height);
        };

        var logo = CreateLogoBox(46, 36, 14);
        logo.Left = (side.Width - logo.Width) / 2;
        logo.Top = 28;
        logo.Anchor = AnchorStyles.Top;
        side.Controls.Add(logo);

        var railText = new VerticalLabel
        {
            Text = "TOOLS",
            ForeColor = BrandBlue,
            Font = new Font("Segoe UI", 8.5f, FontStyle.Bold),
            Width = 34,
            Height = 120,
            Left = 39,
            Top = 132,
            Anchor = AnchorStyles.Top,
        };
        side.Controls.Add(railText);

        var check = new RoundedPanel
        {
            Width = 32,
            Height = 32,
            Left = 40,
            Top = 326,
            Anchor = AnchorStyles.Bottom,
            BorderRadius = 16,
            BorderColor = SoftBorder,
            BackColor = Color.White,
        };
        check.Controls.Add(new Label
        {
            Dock = DockStyle.Fill,
            Text = "✓",
            TextAlign = ContentAlignment.MiddleCenter,
            ForeColor = BrandBlue,
            Font = new Font("Segoe UI", 10f, FontStyle.Bold),
            BackColor = Color.Transparent,
        });
        side.Controls.Add(check);

        side.Resize += (_, _) =>
        {
            logo.Left = (side.Width - logo.Width) / 2;
            railText.Left = (side.Width - railText.Width) / 2;
            check.Left = (side.Width - check.Width) / 2;
            check.Top = Math.Max(280, side.Height - 60);
        };

        return side;
    }

    private Control BuildMainArea()
    {
        var main = new GridPanel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.White,
        };

        var layout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            RowCount = 3,
            ColumnCount = 1,
            BackColor = Color.Transparent,
        };
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 68));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 78));
        main.Controls.Add(layout);

        layout.Controls.Add(BuildTopBar(), 0, 0);
        layout.Controls.Add(BuildContent(), 0, 1);
        layout.Controls.Add(BuildBottomBar(), 0, 2);

        return main;
    }

    private Control BuildTopBar()
    {
        var top = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
            Padding = new Padding(34, 0, 34, 0),
        };
        top.Paint += (_, e) =>
        {
            using var pen = new Pen(Color.FromArgb(237, 240, 244));
            e.Graphics.DrawLine(pen, 0, top.Height - 1, top.Width, top.Height - 1);
        };

        titleLabel.Text = "GarmentsOS PRO";
        titleLabel.Font = new Font("Segoe UI", 10.5f, FontStyle.Bold);
        titleLabel.ForeColor = BrandBlue;
        titleLabel.AutoSize = true;
        titleLabel.BackColor = Color.Transparent;
        titleLabel.Location = new Point(34, 25);
        top.Controls.Add(titleLabel);

        var status = new FlowLayoutPanel
        {
            AutoSize = true,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = false,
            BackColor = Color.Transparent,
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
            Location = new Point(430, 22),
        };
        status.Controls.Add(new StatusDot { DotColor = Color.FromArgb(23, 168, 91), Margin = new Padding(0, 6, 8, 0) });
        status.Controls.Add(new Label
        {
            Text = "Secure Updater",
            AutoSize = true,
            Font = new Font("Segoe UI", 8.4f, FontStyle.Bold),
            ForeColor = Color.FromArgb(61, 75, 96),
            Margin = new Padding(0, 3, 0, 0),
            BackColor = Color.Transparent,
        });
        top.Controls.Add(status);
        top.Resize += (_, _) => status.Left = top.Width - status.Width - 34;

        return top;
    }

    private Control BuildContent()
    {
        var content = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 1,
            Padding = new Padding(34, 30, 34, 18),
            BackColor = Color.Transparent,
        };
        content.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        content.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 220));

        var copy = new Panel { Dock = DockStyle.Fill, BackColor = Color.Transparent };
        copy.Controls.Add(new PillLabel
        {
            Text = "RELEASE FEED",
            Location = new Point(0, 0),
            Width = 112,
            Height = 28,
        });

        var headline = new RichTextLabel
        {
            NormalText = "Preparing your",
            AccentText = "update",
            FontSize = 33f,
            Location = new Point(0, 45),
            Width = 335,
            Height = 82,
        };
        copy.Controls.Add(headline);

        var sub = new Label
        {
            Text = "GarmentsOS PRO is checking release files and preparing a safe local update.",
            Location = new Point(0, 130),
            Width = 335,
            Height = 46,
            Font = new Font("Segoe UI", 9.4f),
            ForeColor = TextMuted,
            BackColor = Color.Transparent,
        };
        copy.Controls.Add(sub);

        var chips = new FlowLayoutPanel
        {
            Location = new Point(0, 190),
            AutoSize = true,
            WrapContents = false,
            BackColor = Color.Transparent,
        };
        chips.Controls.Add(new PillLabel { Text = "Manifest", Width = 78, Height = 28 });
        chips.Controls.Add(new PillLabel { Text = "SHA256", Width = 70, Height = 28 });
        chips.Controls.Add(new PillLabel { Text = "Backup", Width = 68, Height = 28 });
        copy.Controls.Add(chips);

        ConfigureActionButtons();
        copy.Controls.Add(failureButtonsPanel);

        content.Controls.Add(copy, 0, 0);
        content.Controls.Add(BuildPackageVisual(), 1, 0);

        return content;
    }

    private Control BuildPackageVisual()
    {
        var visual = new PackageVisual
        {
            Dock = DockStyle.Fill,
            Logo = TryLoadLogoBitmap(26),
        };
        return visual;
    }

    private Control BuildBottomBar()
    {
        var bottom = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
            Padding = new Padding(34, 0, 34, 23),
        };

        progressStatusLabel.Text = "Preparing update";
        progressStatusLabel.AutoSize = true;
        progressStatusLabel.Font = new Font("Segoe UI", 8.5f, FontStyle.Bold);
        progressStatusLabel.ForeColor = Color.FromArgb(61, 75, 96);
        progressStatusLabel.Location = new Point(34, 0);
        progressStatusLabel.BackColor = Color.Transparent;
        bottom.Controls.Add(progressStatusLabel);

        progressPercentLabel.Text = "0%";
        progressPercentLabel.Font = new Font("Segoe UI", 8.5f, FontStyle.Bold);
        progressPercentLabel.ForeColor = BrandBlue;
        progressPercentLabel.BackColor = Color.Transparent;
        progressPercentLabel.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        progressPercentLabel.Location = new Point(500, 0);
        bottom.Controls.Add(progressPercentLabel);
        bottom.Resize += (_, _) => progressPercentLabel.Left = bottom.Width - progressPercentLabel.Width - 34;

        progressBar.Style = ProgressBarStyle.Continuous;
        progressBar.MarqueeAnimationSpeed = 0;
        progressBar.Value = 0;
        progressBar.Height = 8;
        progressBar.Width = 510;
        progressBar.Location = new Point(34, 24);
        progressBar.Anchor = AnchorStyles.Left | AnchorStyles.Right | AnchorStyles.Top;
        bottom.Controls.Add(progressBar);
        bottom.Resize += (_, _) => progressBar.Width = Math.Max(100, bottom.Width - 68);

        var product = new Label
        {
            Text = "A Product of SparkPair",
            AutoSize = true,
            Font = new Font("Segoe UI", 8.5f),
            ForeColor = TextHint,
            BackColor = Color.Transparent,
            Location = new Point(34, 46),
        };
        bottom.Controls.Add(product);

        installedFooterLabel.Text = "Installed version: checking...";
        installedFooterLabel.Font = new Font("Segoe UI", 8.5f);
        installedFooterLabel.ForeColor = TextHint;
        installedFooterLabel.BackColor = Color.Transparent;
        installedFooterLabel.Anchor = AnchorStyles.Bottom | AnchorStyles.Right;
        installedFooterLabel.Location = new Point(470, 46);
        bottom.Controls.Add(installedFooterLabel);
        bottom.Resize += (_, _) => installedFooterLabel.Left = bottom.Width - installedFooterLabel.Width - 34;

        return bottom;
    }

    private Control BuildProgressPanel()
    {
        var panel = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
        };

        var heading = new Label
        {
            Text = "Updating GarmentsOS PRO",
            Font = new Font("Segoe UI", 16f, FontStyle.Regular),
            ForeColor = TextPrimary,
            AutoSize = true,
            Location = new Point(0, 0),
            BackColor = Color.Transparent,
        };
        panel.Controls.Add(heading);

        progressStatusLabel.Location = new Point(0, 38);
        progressStatusLabel.Font = new Font("Segoe UI", 9f, FontStyle.Bold);
        progressStatusLabel.ForeColor = TextMuted;
        panel.Controls.Add(progressStatusLabel);

        progressBar.Location = new Point(0, 66);
        progressBar.Width = 400;
        progressBar.Height = 8;
        panel.Controls.Add(progressBar);

        detailsButton.Click += (_, _) => ToggleDetails();
        StyleButton(detailsButton, "Details");
        detailsButton.Location = new Point(0, 90);
        panel.Controls.Add(detailsButton);

        return panel;
    }

    private void ConfigureActionButtons()
    {
        buttonsPanel.Controls.Clear();
        buttonsPanel.Location = new Point(0, 231);
        buttonsPanel.AutoSize = true;
        buttonsPanel.WrapContents = false;
        buttonsPanel.BackColor = Color.Transparent;

        StyleButton(updateButton, "Update", primary: true);
        updateButton.Click += async (_, _) => await UpdateNowAsync(requireConfirmation: true, closeAfterSuccess: false);
        buttonsPanel.Controls.Add(updateButton);

        AddButton(buttonsPanel, "Details", (_, _) => ToggleDetails());
        AddButton(buttonsPanel, "Open App", async (_, _) => await OpenAppAsync());
        AddButton(buttonsPanel, "Check", async (_, _) => await CheckUpdateAsync());

        failureButtonsPanel.Controls.Clear();
        failureButtonsPanel.Location = new Point(0, 268);
        failureButtonsPanel.AutoSize = true;
        failureButtonsPanel.WrapContents = false;
        failureButtonsPanel.BackColor = Color.Transparent;
        AddButton(failureButtonsPanel, "Open Install Folder", (_, _) => OpenFolder(installDirBox.Text));
        AddButton(failureButtonsPanel, "Save Log", (_, _) => SaveLog());
        AddButton(failureButtonsPanel, "Close", (_, _) => Close());
        failureButtonsPanel.Visible = false;
    }

    private static void AddButton(Control parent, string text, EventHandler handler)
    {
        var button = new Button();
        StyleButton(button, text);
        button.Click += handler;
        parent.Controls.Add(button);
    }

    private static void StyleButton(Button button, string text, bool primary = false)
    {
        button.Text = text;
        button.AutoSize = false;
        button.Width = primary ? 76 : 72;
        button.Height = 32;
        button.Margin = new Padding(0, 0, 8, 0);
        button.Padding = new Padding(8, 0, 8, 0);
        button.FlatStyle = FlatStyle.Flat;
        button.Cursor = Cursors.Hand;
        button.Font = new Font("Segoe UI", 8.2f, FontStyle.Bold);
        button.BackColor = primary ? BrandBlue : Color.White;
        button.ForeColor = primary ? Color.White : Color.FromArgb(64, 82, 82);
        button.FlatAppearance.BorderColor = primary ? BrandBlue : SoftBorder;
        button.FlatAppearance.BorderSize = 1;
        button.FlatAppearance.MouseOverBackColor = primary ? Color.FromArgb(50, 72, 210) : SurfaceSoft;
    }

    private Control CreateLogoBox(int size, int imageSize, int radius)
    {
        var box = new RoundedPanel
        {
            Width = size,
            Height = size,
            BorderRadius = radius,
            BorderColor = SoftBorder,
            BackColor = Color.White,
        };

        var logo = TryLoadLogoBitmap(imageSize);
        if (logo is not null)
        {
            box.Controls.Add(new PictureBox
            {
                Image = logo,
                Dock = DockStyle.Fill,
                SizeMode = PictureBoxSizeMode.CenterImage,
                BackColor = Color.Transparent,
            });
            return box;
        }

        box.Controls.Add(new Label
        {
            Text = "G",
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter,
            Font = new Font("Segoe UI", 13f, FontStyle.Bold),
            ForeColor = BrandBlue,
            BackColor = Color.Transparent,
        });
        return box;
    }

    private static Bitmap? TryLoadLogoBitmap(int size)
    {
        var candidates = new[]
        {
            Path.Combine(AppContext.BaseDirectory, "garmentsos_pro_logo.png"),
            Path.Combine(AppContext.BaseDirectory, "favicon.ico"),
            Path.Combine(AppContext.BaseDirectory, "favicon(1).ico"),
        };

        foreach (var path in candidates)
        {
            if (!File.Exists(path))
            {
                continue;
            }

            try
            {
                if (Path.GetExtension(path).Equals(".ico", StringComparison.OrdinalIgnoreCase))
                {
                    return new Icon(path, size, size).ToBitmap();
                }

                using var source = Image.FromFile(path);
                return new Bitmap(source, new Size(size, size));
            }
            catch
            {
                // Ignore invalid branding assets and use fallback text.
            }
        }

        return null;
    }

    private void EnterAutoUpdateMode()
    {
        autoUpdateMode = true;
        criticalUpdateStep = false;
        detailsExpanded = false;
        Text = "GarmentsOS PRO Updating";
        titleLabel.Text = "GarmentsOS PRO";
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
        var nextPercent = percent ?? (marquee ? StepPercent(message, progressBar.Value) : progressBar.Value);
        if (percent.HasValue)
        {
            progressBar.Style = ProgressBarStyle.Continuous;
            progressBar.MarqueeAnimationSpeed = 0;
        }
        else if (marquee)
        {
            progressBar.Style = ProgressBarStyle.Continuous;
            progressBar.MarqueeAnimationSpeed = 0;
        }

        progressBar.Value = Math.Clamp(nextPercent, progressBar.Minimum, progressBar.Maximum);
        progressPercentLabel.Text = $"{progressBar.Value}%";
        splashView.ProgressText = message;
        splashView.ProgressPercent = progressBar.Value;
        splashView.Invalidate();
        progressPercentLabel.Left = progressPercentLabel.Parent is null
            ? progressPercentLabel.Left
            : progressPercentLabel.Parent.Width - progressPercentLabel.Width - 34;
    }

    private static int StepPercent(string message, int current)
    {
        if (message.Contains("Downloading", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 38);
        if (message.Contains("Verifying", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 54);
        if (message.Contains("backup", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 64);
        if (message.Contains("Applying", StringComparison.OrdinalIgnoreCase) || message.Contains("Loaded image", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 78);
        if (message.Contains("Restarting", StringComparison.OrdinalIgnoreCase) || message.Contains("started", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 90);
        if (message.Contains("Opening", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 95);
        if (message.Contains("Preparing", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 24);
        return current <= 0 ? 20 : current;
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
        installedFooterLabel.Text = $"Installed version: {manifest?.Version ?? "not found"}";
        splashView.InstalledVersionText = $"Installed version: {manifest?.Version ?? "not found"}";
        splashView.Invalidate();
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

        var query = ProtocolQuery(startupArgument, uri.Query);
        var request = QueryValue(query, "request");
        var autoStart = IsTruthy(QueryValue(query, "autoStart"))
            || IsTruthy(QueryValue(query, "autostart"))
            || IsTruthy(QueryValue(query, "auto"));

        if (autoStart)
        {
            EnterAutoUpdateMode();
            Log("Launcher opened from garmentsos://update auto-start mode.");
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

        currentFeed = null;
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

            SetStep("Verifying package SHA256", marquee: true);
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

    private async Task<bool> HandlePendingLauncherUpdateOnStartupAsync()
    {
        var markerPath = Path.Combine(DefaultInstallDir, ".pending-launcher-update.json");
        if (!File.Exists(markerPath))
        {
            return false;
        }

        Log("Pending launcher update detected on startup.");
        AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher update detected on startup.");

        try
        {
            var marker = await ReadPendingLauncherMarkerAsync(markerPath);
            if (string.IsNullOrWhiteSpace(marker.PendingPath) || !File.Exists(marker.PendingPath))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher path missing: " + marker.PendingPath);
                Log("Pending launcher update could not be applied because the pending EXE was not found.");
                return false;
            }

            if (string.IsNullOrWhiteSpace(marker.DestinationPath))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher destination path missing.");
                Log("Pending launcher update could not be applied because the destination path was missing.");
                return false;
            }

            var destinationDir = Path.GetDirectoryName(marker.DestinationPath);
            if (string.IsNullOrWhiteSpace(destinationDir))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher destination folder missing.");
                Log("Pending launcher update could not be applied because the destination folder was missing.");
                return false;
            }

            Directory.CreateDirectory(destinationDir);

            if (IsCurrentProcessPath(marker.DestinationPath))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Destination launcher is the running process. Starting helper and closing current launcher.");
                StartPendingLauncherReplacementHelper(DefaultInstallDir, relaunchAfterReplace: true, relaunchArgument: startupArgument);
                BeginInvoke(Close);
                return true;
            }

            File.Copy(marker.PendingPath, marker.DestinationPath, overwrite: true);
            File.Delete(marker.PendingPath);
            File.Delete(markerPath);
            RegisterGarmentsProtocol(marker.DestinationPath);
            AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher update applied on startup.");
            Log("Pending launcher update applied on startup.");
            return false;
        }
        catch (Exception ex)
        {
            AppendPendingLauncherLog(DefaultInstallDir, "Startup pending launcher update failed: " + ex.Message);
            Log("Pending launcher update failed on startup: " + ex.Message);
            return false;
        }
    }

    private void StartPendingLauncherReplacementHelper(string installDir, bool relaunchAfterReplace = false, string? relaunchArgument = null)
    {
        var markerPath = Path.Combine(installDir, ".pending-launcher-update.json");
        if (!File.Exists(markerPath))
        {
            return;
        }

        try
        {
            Log("Pending launcher update detected.");
            Log("Launcher update will finish after this updater window closes.");
            var helperPath = Path.Combine(Path.GetTempPath(), "GarmentsOSPendingLauncherUpdate.ps1");
            var relaunchArgumentBase64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(relaunchArgument ?? ""));
            var script = """
                param(
                    [int]$ParentPid,
                    [string]$MarkerPath,
                    [bool]$RelaunchAfterReplace = $false,
                    [string]$RelaunchArgumentBase64 = ""
                )

                $ErrorActionPreference = "Stop"

                function Write-GarmentsPendingLog($Message) {
                    try {
                        $installDir = Split-Path -Parent $MarkerPath
                        $logPath = Join-Path $installDir "pending-launcher-update.log"
                        "[$(Get-Date -Format o)] $Message" | Add-Content -Path $logPath
                    } catch {
                    }
                }

                try {
                    Write-GarmentsPendingLog "helper started"
                    Write-GarmentsPendingLog "waiting for parent: $ParentPid"
                    Wait-Process -Id $ParentPid -ErrorAction SilentlyContinue
                    Write-GarmentsPendingLog "parent exited"
                    Start-Sleep -Seconds 1

                    if (-not (Test-Path -LiteralPath $MarkerPath)) {
                        Write-GarmentsPendingLog "marker not found; nothing to replace"
                        exit 0
                    }

                    $marker = Get-Content -LiteralPath $MarkerPath -Raw | ConvertFrom-Json
                    Write-GarmentsPendingLog "pending path: $($marker.pending_path)"
                    Write-GarmentsPendingLog "destination path: $($marker.destination_path)"

                    if (-not (Test-Path -LiteralPath $marker.pending_path)) {
                        throw "Pending launcher EXE not found: $($marker.pending_path)"
                    }

                    $destinationDir = Split-Path -Parent $marker.destination_path
                    if (-not (Test-Path -LiteralPath $destinationDir)) {
                        New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
                    }

                    Copy-Item -LiteralPath $marker.pending_path -Destination $marker.destination_path -Force
                    Write-GarmentsPendingLog "copy success"
                    Remove-Item -LiteralPath $marker.pending_path -Force -ErrorAction SilentlyContinue
                    Write-GarmentsPendingLog "pending file removed"
                    Remove-Item -LiteralPath $MarkerPath -Force -ErrorAction SilentlyContinue
                    Write-GarmentsPendingLog "marker removed"

                    $launcher = $marker.destination_path
                    $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
                    $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
                    $baseKey.SetValue("URL Protocol", "")
                    $baseKey.Close()

                    $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
                    $commandKey.SetValue("", "`"$launcher`" `"%1`"")
                    $commandKey.Close()
                    Write-GarmentsPendingLog "protocol registered"

                    if ($RelaunchAfterReplace) {
                        $relaunchArgument = ""
                        if (-not [string]::IsNullOrWhiteSpace($RelaunchArgumentBase64)) {
                            $relaunchArgument = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($RelaunchArgumentBase64))
                        }

                        Write-GarmentsPendingLog "relaunch requested"
                        if ([string]::IsNullOrWhiteSpace($relaunchArgument)) {
                            Start-Process -FilePath $launcher -WorkingDirectory (Split-Path -Parent $launcher)
                        } else {
                            Start-Process -FilePath $launcher -ArgumentList @($relaunchArgument) -WorkingDirectory (Split-Path -Parent $launcher)
                        }
                    }
                } catch {
                    Write-GarmentsPendingLog "error: $($_.Exception.Message)"
                }
                """;
            File.WriteAllText(helperPath, script, Encoding.UTF8);
            Process.Start(new ProcessStartInfo
            {
                FileName = "powershell.exe",
                Arguments = $"-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"{helperPath}\" -ParentPid {Environment.ProcessId} -MarkerPath \"{markerPath}\" -RelaunchAfterReplace ${relaunchAfterReplace.ToString().ToLowerInvariant()} -RelaunchArgumentBase64 \"{relaunchArgumentBase64}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            });
            Log("Pending launcher replacement helper started.");
        }
        catch (Exception ex)
        {
            Log("Could not start pending launcher replacement helper: " + ex.Message);
        }
    }

    private sealed class PendingLauncherMarker
    {
        public string PendingPath { get; init; } = "";
        public string DestinationPath { get; init; } = "";
    }

    private static async Task<PendingLauncherMarker> ReadPendingLauncherMarkerAsync(string markerPath)
    {
        await using var stream = File.OpenRead(markerPath);
        using var document = await JsonDocument.ParseAsync(stream);
        var root = document.RootElement;

        return new PendingLauncherMarker
        {
            PendingPath = root.TryGetProperty("pending_path", out var pendingPath) ? pendingPath.GetString() ?? "" : "",
            DestinationPath = root.TryGetProperty("destination_path", out var destinationPath) ? destinationPath.GetString() ?? "" : "",
        };
    }

    private static bool IsCurrentProcessPath(string path)
    {
        try
        {
            var current = Environment.ProcessPath;
            return !string.IsNullOrWhiteSpace(current)
                && string.Equals(Path.GetFullPath(current), Path.GetFullPath(path), StringComparison.OrdinalIgnoreCase);
        }
        catch
        {
            return false;
        }
    }

    private static void RegisterGarmentsProtocol(string launcherPath)
    {
        using var baseKey = Microsoft.Win32.Registry.CurrentUser.CreateSubKey(@"Software\Classes\garmentsos");
        baseKey?.SetValue("", "URL:GarmentsOS PRO Launcher");
        baseKey?.SetValue("URL Protocol", "");

        using var commandKey = Microsoft.Win32.Registry.CurrentUser.CreateSubKey(@"Software\Classes\garmentsos\shell\open\command");
        commandKey?.SetValue("", $"\"{launcherPath}\" \"%1\"");
    }

    private static void AppendPendingLauncherLog(string installDir, string message)
    {
        try
        {
            Directory.CreateDirectory(installDir);
            File.AppendAllText(
                Path.Combine(installDir, "pending-launcher-update.log"),
                $"[{DateTime.Now:O}] {message}{Environment.NewLine}");
        }
        catch
        {
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

    private static bool IsTruthy(string? value)
    {
        return string.Equals(value, "1", StringComparison.OrdinalIgnoreCase)
            || string.Equals(value, "true", StringComparison.OrdinalIgnoreCase)
            || string.Equals(value, "yes", StringComparison.OrdinalIgnoreCase)
            || string.Equals(value, "on", StringComparison.OrdinalIgnoreCase);
    }

    private static string ProtocolQuery(string rawArgument, string parsedQuery)
    {
        var questionIndex = rawArgument.IndexOf('?');
        if (questionIndex < 0 || questionIndex == rawArgument.Length - 1)
        {
            return parsedQuery;
        }

        return rawArgument[(questionIndex + 1)..]
            .Trim()
            .Trim('"')
            .Replace("&amp;", "&", StringComparison.OrdinalIgnoreCase);
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

    private void SetRoundedRegion()
    {
        if (Width <= 0 || Height <= 0)
        {
            return;
        }

        using var path = RoundedRect(new Rectangle(0, 0, Width, Height), 22);
        Region = new Region(path);
    }

    private void BeginDrag(MouseEventArgs e)
    {
        if (e.Button != MouseButtons.Left || criticalUpdateStep)
        {
            return;
        }

        ReleaseCapture();
        SendMessage(Handle, 0xA1, 0x2, 0);
    }

    private static GraphicsPath RoundedRect(Rectangle rect, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(rect.Left, rect.Top, d, d, 180, 90);
        path.AddArc(rect.Right - d, rect.Top, d, d, 270, 90);
        path.AddArc(rect.Right - d, rect.Bottom - d, d, d, 0, 90);
        path.AddArc(rect.Left, rect.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }

    [DllImport("user32.dll")]
    private static extern bool ReleaseCapture();

    [DllImport("user32.dll")]
    private static extern IntPtr SendMessage(IntPtr hWnd, int msg, int wParam, int lParam);
}

internal sealed class UpdaterSplashView : Control
{
    private static readonly Color BrandBlue = Color.FromArgb(37, 99, 235);
    private static readonly Color AppBackground = Color.FromArgb(238, 241, 244);
    private static readonly Color ShellBorder = Color.FromArgb(154, 169, 188);
    private static readonly Color HeaderBorder = Color.FromArgb(214, 221, 231);
    private static readonly Color WindowBorder = Color.FromArgb(135, 152, 173);
    private static readonly Color WindowSoftBorder = Color.FromArgb(174, 185, 200);
    private static readonly Color TextPrimary = Color.FromArgb(7, 16, 31);
    private static readonly Color TextMuted = Color.FromArgb(52, 67, 90);
    private static readonly Color TextHint = Color.FromArgb(108, 122, 144);
    private static readonly Color Track = Color.FromArgb(220, 226, 237);
    private static readonly Color GridLine = Color.FromArgb(4, 37, 99, 235);

    private const string EmbeddedLogoPngBase64 = "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";

    public Image? LogoImage { get; set; }
    public string ProgressText { get; set; } = "Checking update package...";
    public string InstalledVersionText { get; set; } = "Installed version: unknown";
    public int ProgressPercent { get; set; } = 68;

    private readonly Font brandFont = new("Segoe UI", 10.5f, FontStyle.Bold);
    private readonly Font secureFont = new("Segoe UI", 8.2f, FontStyle.Bold);
    private readonly Font titleFont = new("Segoe UI", 24f, FontStyle.Regular);
    private readonly Font bodyFont = new("Segoe UI", 9.4f, FontStyle.Regular);
    private readonly Font pillFont = new("Segoe UI", 8.2f, FontStyle.Bold);
    private readonly Font smallBoldFont = new("Segoe UI", 8.2f, FontStyle.Bold);
    private readonly Font footerFont = new("Segoe UI", 8.3f, FontStyle.Regular);
    private Image? embeddedLogo;

    public UpdaterSplashView()
    {
        DoubleBuffered = true;
        BackColor = AppBackground;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);

        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;

        g.Clear(AppBackground);

        // Same as HTML .shell: 720x420, white, 1px #9aa9bc, radius 20.
        var shell = new Rectangle(0, 0, Width - 1, Height - 1);
        using (var shellPath = SplashRoundedRect(shell, 20))
        using (var shellBrush = new SolidBrush(Color.White))
        using (var shellPen = new Pen(ShellBorder, 1f))
        {
            g.FillPath(shellBrush, shellPath);
            g.DrawPath(shellPen, shellPath);
        }

        // Header 72px.
        using (var sepPen = new Pen(HeaderBorder, 1f))
        {
            g.DrawLine(sepPen, shell.Left, 72, shell.Right, 72);
        }

        // Subtle grid only inside main section, same as .main:before.
        DrawMainGrid(g, new Rectangle(40, 72, 640, 242));

        DrawHeader(g);
        DrawMainContent(g);
        DrawWindowStack(g);
        DrawBottom(g);
    }

    private void DrawHeader(Graphics g)
    {
        // Header padding 40, logo 26 box with 22 image centered.
        DrawLogo(g, 42, 25, 22);
        DrawText(g, "GarmentsOS PRO", brandFont, BrandBlue, 78, 36);

        FillCircle(g, BrandBlue, 582, 39, 7);
        DrawText(g, "Secure Updater", secureFont, TextPrimary, 600, 35);
    }

    private void DrawMainContent(Graphics g)
    {
        // .main padding left 40 + .left padding-left 14.
        var leftX = 54;

        DrawText(g, "Preparing your", titleFont, Color.Black, leftX, 107);
        DrawText(g, "update", titleFont, BrandBlue, leftX, 145);

        DrawText(g, "GarmentsOS PRO is checking release files and", bodyFont, TextMuted, leftX, 205);
        DrawText(g, "preparing a safe local update.", bodyFont, TextMuted, leftX, 228);

        DrawPill(g, new Rectangle(leftX, 268, 100, 28), "Release Feed");
        DrawPill(g, new Rectangle(leftX + 110, 268, 98, 28), "Backup Safe");
        DrawPill(g, new Rectangle(leftX + 218, 268, 70, 28), "Docker");
    }

    private void DrawWindowStack(Graphics g)
    {
        // Same HTML layout:
        // .right transform translate(-22,16), stack front left=0 top=32.
        var rightX = 418;
        var rightY = 120;

        DrawWindowFrame(g, new Rectangle(rightX + 32, rightY + 0, 206, 124), 15, 0.50f, false);
        DrawWindowFrame(g, new Rectangle(rightX + 16, rightY + 16, 206, 124), 15, 0.74f, false);
        DrawWindowFrame(g, new Rectangle(rightX + 0, rightY + 32, 206, 124), 15, 1f, true);
    }

    private void DrawWindowFrame(Graphics g, Rectangle rect, int radius, float opacity, bool front)
    {
        using var path = SplashRoundedRect(rect, radius);
        using var whiteBrush = new SolidBrush(Color.FromArgb((int)(255 * opacity), 255, 255, 255));
        using var borderPen = new Pen(Color.FromArgb((int)(255 * opacity), front ? WindowBorder : WindowSoftBorder), 1f);

        g.FillPath(whiteBrush, path);
        g.DrawPath(borderPen, path);

        if (!front)
        {
            return;
        }

        // .win-head:before left/right/top 4, height 23, radius 9.
        var bar = new Rectangle(rect.X + 4, rect.Y + 4, rect.Width - 8, 23);
        using (var barBrush = new SolidBrush(BrandBlue))
        {
            FillRounded(g, barBrush, bar, 9);
        }

        for (var i = 0; i < 3; i++)
        {
            FillCircle(g, Color.FromArgb(230, 255, 255, 255), rect.X + 13 + (i * 11), rect.Y + 13, 5);
        }

        // Body inner: centered like HTML .inner left 50%, top 45%.
        var bodyTop = rect.Y + 38;
        var bodyHeight = 86;
        var centerX = rect.X + rect.Width / 2;
        var innerCenterY = bodyTop + (int)(bodyHeight * 0.45f);

        var logoTop = innerCenterY - 28;
        DrawLogo(g, centerX - 11, logoTop, 22);

        using var blueBrush = new SolidBrush(BrandBlue);
        using var softBrush = new SolidBrush(Color.FromArgb(88, BrandBlue));
        using var midBrush = new SolidBrush(Color.FromArgb(132, BrandBlue));

        var line1Y = logoTop + 33;
        FillRounded(g, blueBrush, new Rectangle(centerX - 39, line1Y, 78, 4), 2);
        FillRounded(g, softBrush, new Rectangle(centerX - 46, line1Y + 9, 92, 3), 2);
        FillRounded(g, midBrush, new Rectangle(centerX - 24, line1Y + 19, 48, 2), 2);
    }

    private void DrawBottom(Graphics g)
    {
        // .bottom padding 14px 40px 30px 40px.
        var bottomX = 40;
        DrawText(g, ProgressText, smallBoldFont, TextMuted, bottomX, 331);
        DrawTextRight(g, $"{Math.Clamp(ProgressPercent, 0, 100)}%", smallBoldFont, BrandBlue, 680, 331);

        var trackRect = new Rectangle(bottomX, 360, 640, 3);
        using (var trackBrush = new SolidBrush(Track))
        {
            FillRounded(g, trackBrush, trackRect, 2);
        }

        var fillWidth = (int)(trackRect.Width * Math.Clamp(ProgressPercent, 0, 100) / 100f);
        if (fillWidth > 0)
        {
            using var fillBrush = new SolidBrush(BrandBlue);
            FillRounded(g, fillBrush, new Rectangle(trackRect.X, trackRect.Y, fillWidth, trackRect.Height), 2);
        }

        DrawText(g, "A Product of SparkPair", footerFont, TextHint, bottomX, 381);
        DrawTextRight(g, InstalledVersionText, footerFont, TextHint, 680, 381);
    }

    private void DrawMainGrid(Graphics g, Rectangle main)
    {
        using var gridPen = new Pen(GridLine, 1f);
        for (var x = main.Left; x <= main.Right; x += 58)
        {
            g.DrawLine(gridPen, x, main.Top, x, main.Bottom);
        }

        for (var y = main.Top; y <= main.Bottom; y += 58)
        {
            g.DrawLine(gridPen, main.Left, y, main.Right, y);
        }
    }

    private void DrawPill(Graphics g, Rectangle rect, string text)
    {
        using (var brush = new SolidBrush(Color.White))
        using (var pen = new Pen(WindowSoftBorder, 1f))
        using (var path = SplashRoundedRect(rect, 11))
        {
            g.FillPath(brush, path);
            g.DrawPath(pen, path);
        }

        using var sf = new StringFormat { Alignment = StringAlignment.Center, LineAlignment = StringAlignment.Center };
        using var textBrush = new SolidBrush(Color.FromArgb(30, 42, 61));
        g.DrawString(text, pillFont, textBrush, rect, sf);
    }

    private void DrawLogo(Graphics g, int x, int y, int size)
    {
        var logo = GetEmbeddedLogo();
        if (logo is not null)
        {
            g.DrawImage(logo, new Rectangle(x, y, size, size));
            return;
        }

        // Fallback only if embedded PNG is unavailable.
        DrawBrandMark(g, x, y, size);
    }

    private Image? GetEmbeddedLogo()
    {
        if (embeddedLogo is not null)
        {
            return embeddedLogo;
        }

        if (string.IsNullOrWhiteSpace(EmbeddedLogoPngBase64))
        {
            return null;
        }

        try
        {
            var bytes = Convert.FromBase64String(EmbeddedLogoPngBase64);
            var stream = new MemoryStream(bytes);
            embeddedLogo = Image.FromStream(stream);
            return embeddedLogo;
        }
        catch
        {
            return null;
        }
    }

    private void DrawBrandMark(Graphics g, int x, int y, int size)
    {
        using var brush = new SolidBrush(BrandBlue);

        var r = new Rectangle(x, y, size, size);
        var leftBlob = new Rectangle(r.X, r.Y + 1, (int)(size * 0.66), size - 2);
        var topBlob = new Rectangle(r.X + (int)(size * 0.56), r.Y, (int)(size * 0.38), (int)(size * 0.38));
        var bottomBlob = new Rectangle(r.X + (int)(size * 0.52), r.Y + (int)(size * 0.52), (int)(size * 0.45), (int)(size * 0.42));

        using (var leftPath = new GraphicsPath())
        {
            leftPath.AddPie(leftBlob, 90, 270);
            g.FillPath(brush, leftPath);
        }

        g.FillEllipse(brush, topBlob);
        FillRounded(g, brush, bottomBlob, Math.Max(4, size / 5));
    }

    private static void DrawText(Graphics g, string text, Font font, Color color, int x, int y)
    {
        using var brush = new SolidBrush(color);
        g.DrawString(text, font, brush, x, y);
    }

    private static void DrawTextRight(Graphics g, string text, Font font, Color color, int right, int y)
    {
        using var brush = new SolidBrush(color);
        using var format = new StringFormat { Alignment = StringAlignment.Far };
        g.DrawString(text, font, brush, new RectangleF(0, y, right, 24), format);
    }

    private static void FillCircle(Graphics g, Color color, int x, int y, int size)
    {
        using var brush = new SolidBrush(color);
        g.FillEllipse(brush, x, y, size, size);
    }

    private static void FillRounded(Graphics g, Brush brush, Rectangle rect, int radius)
    {
        using var path = SplashRoundedRect(rect, radius);
        g.FillPath(brush, path);
    }

    private static GraphicsPath SplashRoundedRect(Rectangle rect, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(rect.Left, rect.Top, d, d, 180, 90);
        path.AddArc(rect.Right - d, rect.Top, d, d, 270, 90);
        path.AddArc(rect.Right - d, rect.Bottom - d, d, d, 0, 90);
        path.AddArc(rect.Left, rect.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            embeddedLogo?.Dispose();
            brandFont.Dispose();
            secureFont.Dispose();
            titleFont.Dispose();
            bodyFont.Dispose();
            pillFont.Dispose();
            smallBoldFont.Dispose();
            footerFont.Dispose();
        }

        base.Dispose(disposing);
    }
}

internal sealed class RoundedPanel : Panel
{
    public int BorderRadius { get; set; } = 18;
    public Color BorderColor { get; set; } = Color.FromArgb(199, 208, 220);

    public RoundedPanel()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        using var path = RoundedRect(new Rectangle(0, 0, Width - 1, Height - 1), BorderRadius);
        using var pen = new Pen(BorderColor, 1f);
        e.Graphics.DrawPath(pen, path);
    }

    protected override void OnResize(EventArgs eventargs)
    {
        base.OnResize(eventargs);
        if (Width <= 0 || Height <= 0)
        {
            return;
        }

        using var path = RoundedRect(new Rectangle(0, 0, Width, Height), BorderRadius);
        Region = new Region(path);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

internal sealed class GridPanel : Panel
{
    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        using var pen = new Pen(Color.FromArgb(5, 64, 87, 232));
        for (var x = 0; x < Width; x += 56)
        {
            e.Graphics.DrawLine(pen, x, 0, x, Height);
        }
        for (var y = 0; y < Height; y += 56)
        {
            e.Graphics.DrawLine(pen, 0, y, Width, y);
        }
    }
}

internal sealed class StatusDot : Control
{
    public Color DotColor { get; set; } = Color.FromArgb(64, 87, 232);

    public StatusDot()
    {
        Width = 7;
        Height = 7;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        using var brush = new SolidBrush(DotColor);
        e.Graphics.FillEllipse(brush, 0, 0, Width - 1, Height - 1);
    }
}

internal sealed class VerticalLabel : Control
{
    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        using var brush = new SolidBrush(ForeColor);
        using var format = new StringFormat { Alignment = StringAlignment.Center, LineAlignment = StringAlignment.Center };
        e.Graphics.TranslateTransform(Width / 2f, Height / 2f);
        e.Graphics.RotateTransform(-90);
        e.Graphics.DrawString(Text, Font, brush, new RectangleF(-Height / 2f, -Width / 2f, Height, Width), format);
        e.Graphics.ResetTransform();
    }
}

internal sealed class PillLabel : Label
{
    public PillLabel()
    {
        AutoSize = false;
        TextAlign = ContentAlignment.MiddleCenter;
        Font = new Font("Segoe UI", 8.2f, FontStyle.Bold);
        ForeColor = Color.FromArgb(64, 82, 82);
        BackColor = Color.White;
        Margin = new Padding(0, 0, 8, 0);
        Padding = new Padding(8, 0, 8, 0);
        SetStyle(ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        var rect = new Rectangle(0, 0, Width - 1, Height - 1);
        using var path = RoundedRect(rect, Height / 2);
        using var bg = new SolidBrush(BackColor);
        using var pen = new Pen(Color.FromArgb(199, 208, 220));
        e.Graphics.FillPath(bg, path);
        e.Graphics.DrawPath(pen, path);
        TextRenderer.DrawText(e.Graphics, Text, Font, rect, ForeColor, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

internal sealed class RichTextLabel : Control
{
    public string NormalText { get; set; } = "";
    public string AccentText { get; set; } = "";
    public float FontSize { get; set; } = 33f;

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;
        using var normalFont = new Font("Segoe UI", FontSize, FontStyle.Regular);
        using var accentFont = new Font("Segoe UI", FontSize, FontStyle.Regular);
        using var normal = new SolidBrush(Color.FromArgb(15, 23, 42));
        using var accent = new SolidBrush(Color.FromArgb(64, 87, 232));
        e.Graphics.DrawString(NormalText, normalFont, normal, 0, 0);
        e.Graphics.DrawString(AccentText, accentFont, accent, 0, FontSize + 5);
    }
}

internal sealed class PackageVisual : Control
{
    public Bitmap? Logo { get; set; }

    public PackageVisual()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;

        var card = new Rectangle(6, 8, 205, 135);
        using (var shadow = new SolidBrush(Color.FromArgb(12, 64, 87, 232)))
        {
            g.FillRoundedRectangle(shadow, new Rectangle(card.X + 8, card.Y + 10, card.Width, card.Height), 22);
        }
        DrawRounded(g, card, Color.White, Color.FromArgb(174, 185, 202), 22);

        using (var pen = new Pen(Color.FromArgb(215, 221, 231)))
        {
            g.DrawLine(pen, card.Left, card.Top + 38, card.Right, card.Top + 38);
        }

        using (var dot = new SolidBrush(Color.FromArgb(195, 202, 212)))
        using (var blue = new SolidBrush(Color.FromArgb(64, 87, 232)))
        {
            g.FillEllipse(dot, card.Left + 14, card.Top + 16, 6, 6);
            g.FillEllipse(dot, card.Left + 27, card.Top + 16, 6, 6);
            g.FillEllipse(blue, card.Left + 40, card.Top + 16, 6, 6);
        }
        using (var blueBrush = new SolidBrush(Color.FromArgb(64, 87, 232)))
        using (var font = new Font("Segoe UI", 7.8f, FontStyle.Bold))
        {
            g.DrawString("v1.8.25", font, blueBrush, card.Right - 54, card.Top + 12);
        }

        var fileRow = new Rectangle(card.Left + 14, card.Top + 52, 177, 50);
        DrawRounded(g, fileRow, Color.FromArgb(248, 250, 252), Color.FromArgb(211, 218, 229), 15);
        var logoRect = new Rectangle(fileRow.Left + 11, fileRow.Top + 8, 34, 34);
        DrawRounded(g, logoRect, Color.White, Color.FromArgb(211, 218, 229), 11);
        if (Logo is not null)
        {
            g.DrawImage(Logo, new Rectangle(logoRect.Left + 4, logoRect.Top + 4, 26, 26));
        }
        else
        {
            using var f = new Font("Segoe UI", 10f, FontStyle.Bold);
            using var b = new SolidBrush(Color.FromArgb(64, 87, 232));
            g.DrawString("G", f, b, logoRect.Left + 9, logoRect.Top + 7);
        }

        using (var blueLine = new SolidBrush(Color.FromArgb(56, 64, 87, 232)))
        using (var line = new SolidBrush(Color.FromArgb(219, 225, 234)))
        {
            g.FillRoundedRectangle(blueLine, new Rectangle(fileRow.Left + 56, fileRow.Top + 15, 64, 4), 2);
            g.FillRoundedRectangle(line, new Rectangle(fileRow.Left + 56, fileRow.Top + 27, 42, 4), 2);
        }

        using (var tiny = new Font("Segoe UI", 7.4f, FontStyle.Bold))
        using (var muted = new SolidBrush(Color.FromArgb(83, 97, 113)))
        using (var blueText = new SolidBrush(Color.FromArgb(64, 87, 232)))
        {
            g.DrawString("FILE", tiny, muted, card.Left + 14, card.Top + 112);
            g.DrawString("Ready", tiny, blueText, card.Right - 61, card.Top + 112);
        }
    }

    private static void DrawRounded(Graphics g, Rectangle rect, Color fill, Color border, int radius)
    {
        using var path = RoundedRect(rect, radius);
        using var brush = new SolidBrush(fill);
        using var pen = new Pen(border);
        g.FillPath(brush, path);
        g.DrawPath(pen, path);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

internal static class GraphicsExtensions
{
    public static void FillRoundedRectangle(this Graphics graphics, Brush brush, Rectangle bounds, int radius)
    {
        using var path = RoundedRect(bounds, radius);
        graphics.FillPath(brush, path);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

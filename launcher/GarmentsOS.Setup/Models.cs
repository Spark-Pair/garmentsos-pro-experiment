using System.Text.Json.Serialization;

namespace GarmentsOS.Setup;

public sealed class InstalledManifest
{
    [JsonPropertyName("app")]
    public string App { get; set; } = "";

    [JsonPropertyName("version")]
    public string Version { get; set; } = "";

    [JsonPropertyName("channel")]
    public string Channel { get; set; } = "";
}

public sealed class ReleaseFeed
{
    [JsonPropertyName("app")]
    public string App { get; set; } = "";

    [JsonPropertyName("version")]
    public string Version { get; set; } = "";

    [JsonPropertyName("channel")]
    public string Channel { get; set; } = "";

    [JsonPropertyName("mandatory")]
    public bool Mandatory { get; set; }

    [JsonPropertyName("released_at")]
    public string ReleasedAt { get; set; } = "";

    [JsonPropertyName("package_file")]
    public string PackageFile { get; set; } = "";

    [JsonPropertyName("package_sha256")]
    public string PackageSha256 { get; set; } = "";

    [JsonPropertyName("package_url")]
    public string PackageUrl { get; set; } = "";

    [JsonPropertyName("notes")]
    public string Notes { get; set; } = "";
}

public sealed class UpdateRequest
{
    [JsonPropertyName("request_id")]
    public string RequestId { get; set; } = "";

    [JsonPropertyName("current_version")]
    public string CurrentVersion { get; set; } = "";

    [JsonPropertyName("target_version")]
    public string TargetVersion { get; set; } = "";

    [JsonPropertyName("channel")]
    public string Channel { get; set; } = "";

    [JsonPropertyName("package_file")]
    public string PackageFile { get; set; } = "";

    [JsonPropertyName("package_url")]
    public string PackageUrl { get; set; } = "";

    [JsonPropertyName("package_sha256")]
    public string PackageSha256 { get; set; } = "";

    [JsonPropertyName("setup_url")]
    public string SetupUrl { get; set; } = "";

    [JsonPropertyName("update_lock_failed_url")]
    public string UpdateLockFailedUrl { get; set; } = "";

    [JsonPropertyName("mandatory")]
    public bool Mandatory { get; set; }

    [JsonPropertyName("notes")]
    public string Notes { get; set; } = "";
}

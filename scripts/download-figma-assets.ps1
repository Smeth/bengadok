# Script pour télécharger les assets Figma du Dashboard Admin
# Les URLs expirent après 7 jours

$baseDir = Join-Path $PSScriptRoot "..\public\images\figma-assets"
if (-not (Test-Path $baseDir)) { New-Item -ItemType Directory -Path $baseDir -Force | Out-Null }

$assets = @(
    @{ url = "https://www.figma.com/api/mcp/asset/547e8cb9-2217-4a6a-95ce-071516934325"; name = "icon-dashboard" },
    @{ url = "https://www.figma.com/api/mcp/asset/2450d45e-7b7e-420f-ae0c-94f0d1312ff6"; name = "icon-users" },
    @{ url = "https://www.figma.com/api/mcp/asset/153988bb-a61f-43ad-9209-aac23b189bdc"; name = "icon-package" },
    @{ url = "https://www.figma.com/api/mcp/asset/f4388320-90bd-4b0d-b6e0-aa9fa73c9c54"; name = "icon-settings" },
    @{ url = "https://www.figma.com/api/mcp/asset/ccc9055d-2c58-4593-b17e-c119c40f9708"; name = "icon-orders" },
    @{ url = "https://www.figma.com/api/mcp/asset/99e47349-4a05-434c-bc73-3355a9ca8f88"; name = "icon-pharmacy" },
    @{ url = "https://www.figma.com/api/mcp/asset/a5eb8264-1feb-40fe-913c-27faba5fb12a"; name = "icon-logout" },
    @{ url = "https://www.figma.com/api/mcp/asset/ce9dea9b-f670-4920-b3e4-491134122ab3"; name = "sidebar-logo" },
    # sidebar-logo-benga : fichier manuel public/images/figma-assets/sidebar-logo-benga.png (pas d’URL Figma stable)
    @{ url = "https://www.figma.com/api/mcp/asset/89b41da6-2a13-417a-a778-435e312cddfe"; name = "hero-icon-1" },
    @{ url = "https://www.figma.com/api/mcp/asset/44ae880f-fc10-4063-9141-c170f53c3d43"; name = "hero-icon-2" },
    @{ url = "https://www.figma.com/api/mcp/asset/479e44bb-5f5b-41af-a909-7b2fe9266b19"; name = "hero-icon-3" },
    @{ url = "https://www.figma.com/api/mcp/asset/836df22c-6248-4af2-9e71-77eb69da8e21"; name = "hero-icon-4" },
    @{ url = "https://www.figma.com/api/mcp/asset/4b5df136-912c-4a04-9d73-c7567bb3c42a"; name = "hero-icon-5" },
    @{ url = "https://www.figma.com/api/mcp/asset/483431a9-ffc4-4d21-875a-044f210eb62d"; name = "hero-icon-6" },
    @{ url = "https://www.figma.com/api/mcp/asset/9fbbe1fa-0e37-48d7-bcae-9f24013442a9"; name = "hero-icon-7" },
    @{ url = "https://www.figma.com/api/mcp/asset/04ed1d43-b67b-45e3-ac6c-f2ff2be62f4a"; name = "hero-icon-8" },
    @{ url = "https://www.figma.com/api/mcp/asset/83d808f2-a113-4e94-ba6b-d7aa859de1bf"; name = "sidebar-doctor" },
    # Illustration sidebar (docteur + 4 icônes flottantes)
    @{ url = "https://www.figma.com/api/mcp/asset/b9bd748f-ee26-4c42-a0f0-6b8d726f1fff"; name = "sidebar-doctor-main" },
    @{ url = "https://www.figma.com/api/mcp/asset/0541f936-4fb8-408a-b1d8-07e2f73215ce"; name = "sidebar-icon-caduceus" },
    @{ url = "https://www.figma.com/api/mcp/asset/099d28bf-9221-488b-8a17-f963d9408ff5"; name = "sidebar-icon-pills" },
    @{ url = "https://www.figma.com/api/mcp/asset/a01d77f9-39c2-4537-a179-73b9b387779a"; name = "sidebar-icon-bottle" },
    @{ url = "https://www.figma.com/api/mcp/asset/38d32d80-d7b3-4344-a754-90aed51b3f10"; name = "sidebar-icon-barchart" },
    @{ url = "https://www.figma.com/api/mcp/asset/6fcab0c8-945e-40fc-8dc8-83ecaec47fb5"; name = "chevron-down" },
    @{ url = "https://www.figma.com/api/mcp/asset/c3f76aad-14dc-45ea-8341-d634662a55fd"; name = "kpi-arrow-up" },
    @{ url = "https://www.figma.com/api/mcp/asset/6725e6a4-0731-4ded-bba9-9f8d696cf8cd"; name = "kpi-arrow-down" },
    @{ url = "https://www.figma.com/api/mcp/asset/42f16004-93d7-402d-8e34-4ee25cdabc28"; name = "avatar-placeholder" }
)

$count = 0
foreach ($a in $assets) {
    try {
        $path = Join-Path $baseDir $a.name
        Invoke-WebRequest -Uri $a.url -OutFile $path -UseBasicParsing
        $bytes = [System.IO.File]::ReadAllBytes($path)
        $isPng = ($bytes.Length -ge 8) -and ($bytes[0] -eq 0x89) -and ($bytes[1] -eq 0x50) -and ($bytes[2] -eq 0x4E)
        $ext = if ($isPng) { ".png" } else { ".svg" }
        $newName = $a.name + $ext
        Rename-Item -Path $path -NewName $newName -Force
        $count++
        Write-Host "OK: $($a.name)$ext"
    } catch {
        Write-Host "ERREUR: $($a.name) - $_"
    }
}
Write-Host "`nTelecharge: $count / $($assets.Count) assets"

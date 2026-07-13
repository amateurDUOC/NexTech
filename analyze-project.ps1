#!/usr/bin/env pwsh

# Script para ejecutar análisis completo con SonarQube
# Uso: .\analyze-project.ps1 -Token "TU_TOKEN_SONARCLOUD"

param(
    [Parameter(Mandatory=$true)]
    [string]$Token,
    
    [string]$ProjectKey = "nextech-wordpress",
    [string]$Host = "https://sonarcloud.io",
    [string]$ProjectPath = "."
)

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SonarQube Analysis - NexTech WordPress" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Verificar que SonarScanner esté instalado
Write-Host "Verificando SonarScanner..." -ForegroundColor Yellow
try {
    $version = sonar-scanner -v
    Write-Host "✓ SonarScanner $version encontrado" -ForegroundColor Green
} catch {
    Write-Host "✗ SonarScanner no encontrado. Instálalo con:" -ForegroundColor Red
    Write-Host "  npm install -g sonarqube-scanner" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# Mostrar configuración
Write-Host "Configuración:" -ForegroundColor Yellow
Write-Host "  Proyecto: $ProjectKey"
Write-Host "  Host: $Host"
Write-Host "  Ruta: $ProjectPath"
Write-Host ""

# Ejecutar análisis
Write-Host "Iniciando análisis..." -ForegroundColor Cyan
Write-Host ""

sonar-scanner `
  -Dsonar.projectKey=$ProjectKey `
  -Dsonar.sources=$ProjectPath `
  -Dsonar.exclusions="wp-admin/**,wp-includes/**,wp-content/**,node_modules/**,.git/**,vendor/**" `
  -Dsonar.host.url=$Host `
  -Dsonar.login=$Token

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "✓ Análisis completado exitosamente" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Ver resultados en:" -ForegroundColor Yellow
    Write-Host "  $Host/dashboard?id=$ProjectKey"
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "✗ Error durante el análisis" -ForegroundColor Red
    exit 1
}

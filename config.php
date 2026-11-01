<?php
return [
    'titulo' => 'Central de Ferramentas',

    // Os usuários e senhas ficam em usuarios/usuarios.json (gerencie pela página "usuários").

    // Backups automáticos: a cada salvamento a versão anterior é guardada em <ferramenta>/backups/
    'backup' => [
        'max_versoes' => 30,   // no máximo N versões por arquivo
        'dias' => 365,          // versões mais velhas que isso são apagadas...
        'min_versoes' => 5,    // ...mas as N mais recentes são sempre mantidas
    ],

    // Backup geral (zip com os dados de todas as ferramentas) gravado em backups_gerais/
    'backup_geral' => [
        'dias' => 30,      // faz um automático a cada N dias (0 desliga)
        'manter' => 12,    // quantos zips guardar
    ],

    // Arquivos excluídos vão para <ferramenta>/lixeira/ e somem depois de N dias
    'lixeira_dias' => 60,

    // Caminho do LibreOffice (para converter em PDF). Deixe '' para detectar automaticamente.
    'soffice' => '',
];

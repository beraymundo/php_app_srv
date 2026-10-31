<?php
return [
    'titulo' => 'Central de Ferramentas',

    // Senha opcional. Deixe '' para não pedir senha (uso só no seu PC).
    // Se for acessar pelo celular na rede de casa, é bom definir uma.
    'senha' => '',

    // Backups automáticos: a cada salvamento a versão anterior é guardada em <ferramenta>/backups/
    'backup' => [
        'max_versoes' => 30,   // no máximo N versões por arquivo
        'dias' => 365,          // versões mais velhas que isso são apagadas...
        'min_versoes' => 5,    // ...mas as N mais recentes são sempre mantidas
    ],

    // Arquivos excluídos vão para <ferramenta>/lixeira/ e somem depois de N dias
    'lixeira_dias' => 60,

    // Caminho do LibreOffice (para converter em PDF). Deixe '' para detectar automaticamente.
    'soffice' => '',
];

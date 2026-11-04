<?php

/**
 * Regras puras (sem banco/HTTP) da PROPOSTA do prestador por link e do checklist
 * de documentos por tipo de contratação (CLT vs PJ).
 *
 * O prestador segue o mesmo espírito da proposta do cliente: recebe um link,
 * aceita ou recusa (com motivo). Aqui ficam as decisões testáveis — quais
 * documentos são exigidos por tipo de contratação e os textos das mensagens.
 */
class ProviderProposalRules
{
    /**
     * Checklist de documentos exigidos conforme o tipo de contratação.
     * CLT: documentos pessoais e admissionais. PJ/MEI: documentos da empresa.
     * Demais tipos: um conjunto mínimo.
     *
     * @return array<int,array{key:string,label:string}>
     */
    public static function requiredDocuments(?string $engagementType): array
    {
        $type = ProviderRules::normalizeEngagement($engagementType);
        if ($type === 'clt') {
            return [
                ['key' => 'rg_cpf',            'label' => 'RG e CPF'],
                ['key' => 'comprovante_resid', 'label' => 'Comprovante de residência'],
                ['key' => 'ctps',              'label' => 'Carteira de trabalho (CTPS)'],
                ['key' => 'titulo_eleitor',    'label' => 'Título de eleitor'],
                ['key' => 'dados_bancarios',   'label' => 'Dados bancários'],
                ['key' => 'exame_admissional', 'label' => 'Exame admissional'],
                ['key' => 'foto_3x4',          'label' => 'Foto 3x4'],
            ];
        }
        if ($type === 'pj') {
            return [
                ['key' => 'cnpj',            'label' => 'Cartão CNPJ ativo'],
                ['key' => 'contrato_social', 'label' => 'Contrato social / MEI'],
                ['key' => 'rg_cpf_socio',    'label' => 'RG e CPF do sócio responsável'],
                ['key' => 'dados_bancarios', 'label' => 'Dados bancários (PJ)'],
            ];
        }
        // freelancer / estagio / outro: mínimo essencial.
        return [
            ['key' => 'rg_cpf',          'label' => 'RG e CPF'],
            ['key' => 'dados_bancarios', 'label' => 'Dados bancários'],
        ];
    }

    /**
     * A partir das linhas de provider_documents (com doc_type) e do tipo de
     * contratação, retorna o checklist com o que falta. Um documento é
     * considerado presente quando há uma linha com o doc_type correspondente.
     *
     * @param array<int,array> $documents linhas de provider_documents
     * @return array<int,array{key:string,label:string,present:bool}>
     */
    public static function documentChecklist(?string $engagementType, array $documents): array
    {
        $have = [];
        foreach ($documents as $d) {
            $t = trim((string)($d['doc_type'] ?? ''));
            if ($t !== '') $have[$t] = true;
        }
        $out = [];
        foreach (self::requiredDocuments($engagementType) as $req) {
            $out[] = ['key' => $req['key'], 'label' => $req['label'], 'present' => isset($have[$req['key']])];
        }
        return $out;
    }

    /**
     * Valida o registro de um documento. O tipo é obrigatório (checklist ou
     * 'outro'); nome vazio assume o nome do tipo, exceto em 'outro'.
     *
     * @return array{doc_type:string,doc_label:string}|array{error:string}
     */
    public static function normalizeDocumentEntry(?string $engagementType, $docType, $label): array
    {
        $type = trim((string)$docType);
        $label = trim((string)$label);
        if ($type === '') return ['error' => 'Selecione o tipo do documento.'];
        $byKey = array_column(self::requiredDocuments($engagementType), 'label', 'key');
        if ($type !== 'outro' && !isset($byKey[$type])) return ['error' => 'Tipo de documento inválido.'];
        if ($label === '') $label = $byKey[$type] ?? '';
        if ($label === '') return ['error' => 'Informe o nome do documento.'];
        return ['doc_type' => $type, 'doc_label' => $label];
    }

    /**
     * Nome legível de um tipo de documento. Procura também nos outros tipos de
     * contratação, pois o prestador pode ter mudado depois do registro.
     */
    public static function documentTypeLabel(?string $docType, ?string $engagementType = null): ?string
    {
        $type = trim((string)$docType);
        if ($type === '') return null;
        if ($type === 'outro') return 'Outro';
        foreach ([$engagementType, 'clt', 'pj', 'freelancer'] as $engagement) {
            $byKey = array_column(self::requiredDocuments($engagement), 'label', 'key');
            if (isset($byKey[$type])) return $byKey[$type];
        }
        return $type;
    }

    /** Chaves de documentos ainda ausentes (bloqueiam a conclusão da admissão). */
    public static function missingDocuments(?string $engagementType, array $documents): array
    {
        $out = [];
        foreach (self::documentChecklist($engagementType, $documents) as $item) {
            if (!$item['present']) $out[] = $item['label'];
        }
        return $out;
    }

    /** Motivo de recusa saneado (obrigatório). Null se vazio. */
    public static function sanitizeRejectReason($reason): ?string
    {
        $r = trim((string)$reason);
        return $r === '' ? null : mb_substr($r, 0, 1000);
    }

    /** Mensagem de WhatsApp ao prestador com o link da proposta. */
    public static function proposalWhatsapp(string $name, string $link, ?string $roleTitle, ?string $company): string
    {
        $msg = "Olá" . ($name !== '' ? ", {$name}" : '') . "!\n\n";
        if ($company) $msg .= "*{$company}*\n";
        $msg .= "Preparamos uma proposta de parceria" . ($roleTitle ? " para a função de {$roleTitle}" : '') . ". ";
        $msg .= "Veja os detalhes e responda (aceitar ou recusar) pelo link:\n{$link}";
        return $msg;
    }

    public static function proposalEmailSubject(?string $company): string
    {
        return 'Proposta de parceria' . ($company ? " — {$company}" : '');
    }

    public static function proposalEmailBody(string $name, string $link, ?string $roleTitle): string
    {
        $safeName = htmlspecialchars($name !== '' ? $name : 'Olá');
        $safeLink = htmlspecialchars($link);
        $role = $roleTitle ? ' para a função de <strong>' . htmlspecialchars($roleTitle) . '</strong>' : '';
        return "<p>Olá, <strong>{$safeName}</strong>!</p>"
            . "<p>Preparamos uma proposta de parceria{$role}.</p>"
            . "<p style='text-align:center;margin:24px 0;'>"
            . "<a href='{$safeLink}' style='background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;'>Ver proposta</a></p>"
            . "<p>No link você pode aceitar ou recusar (informando o motivo). Qualquer dúvida, estamos à disposição.</p>";
    }
}

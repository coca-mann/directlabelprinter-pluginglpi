# Changelog

Todas as mudanças notáveis deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added

- [8be5b37](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/8be5b37) - Adicionado suporte à impressão de etiquetas para Racks e Insumos.

### Changed

- [142727f](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/142727f), [37bf37b](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/37bf37b) - Consolidadas em uma única entrada de menu ("Config. Imp. Etiquetas") as telas de Servidores de Impressão e Layouts, antes exibidas como dois itens separados em Configuração; o caminho de volta para essa entrada agora aparece no breadcrumb das telas de lista e formulário.
- [eb1e3f8](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/eb1e3f8) - Reorganizada a seção "Tipos de ativo" no formulário de Layout em uma seção própria, com os tipos e a opção "Padrão" alinhados em colunas e exibindo o nome nativo traduzido do GLPI para cada itemtype.

### Fixed

- [1feb51f](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/1feb51f) - Corrigido o upload de fonte personalizada (`.ttf`) em instalações limpas do GLPI, que não vinham com esse tipo de documento liberado para envio.

## [0.2.1] - 2026-08-24

### Added

- [92cea77](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/92cea77) - Adicionado processo de changelog e versionamento do projeto (`CHANGELOG.md`, `docs/versioning.md` e template de PR no GitHub).

### Changed

- [9aaee0b](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/9aaee0b) - Removida a pasta `tudo_old/`, um protótipo de página de configuração que nunca chegou a ser integrado ao plugin.

<!--
Ao criar uma nova tag:
1. Renomeie a seção acima para incluir a versão:
   ## [Unreleased]

   ## [X.Y.Z] - AAAA-MM-DD
   ### Added
   - ...
2. Copie o conteúdo da seção "Changelog" do(s) MR(s)/PR(s) incluídos na tag.
3. Preencha a data no formato AAAA-MM-DD.
-->

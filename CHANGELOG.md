# Changelog

Todas as mudanças notáveis deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added

### Changed

### Fixed

### Security

## [0.4.0] - 2026-08-24

### Fixed

- [8400c91](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/8400c91) - Corrigido o atalho de engrenagem em Configuração > Plug-ins, que abria direto a lista de servidores de impressão em vez do hub de configuração do plugin.

### Security

- [a084bda](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/a084bda) - Adicionado controle de permissão dedicado para a ação "Imprimir Etiqueta" e para as telas de configuração de servidores/layouts, ajustável em Administração > Perfis; antes, qualquer usuário com acesso de leitura ao ativo podia imprimir etiquetas livremente, sem nenhum right do plugin envolvido.

## [0.3.0] - 2026-08-24

### Added

- [8be5b37](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/8be5b37) - Adicionado suporte à impressão de etiquetas para Racks e Insumos.
- [09faa8e](https://github.com/coca-mann/directlabelprinter-pluginglpi/commit/09faa8e) - Adicionado suporte à impressão de etiquetas para ativos customizados (Configuração > Definições de ativos), detectados automaticamente sem precisar editar o plugin.

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
Este changelog não mantém uma seção [Unreleased] vazia entre releases — ela só
existe temporariamente, criada pelo /changelog-pr quando há mudança para
registrar, e é renomeada para a versão (/changelog-release) sem deixar uma
nova seção vazia no lugar. Cada entrada segue o formato `<hash(es)> - <descrição>`,
com o(s) hash(es) linkados pro commit no GitHub, só nas categorias
(Added/Changed/Fixed/Security) que tiverem pelo menos uma entrada.
-->

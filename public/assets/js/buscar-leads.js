document.addEventListener('DOMContentLoaded', function () {
    const searchForm = document.getElementById('lead-search-form');
    const cnaeInput = document.getElementById('search-cnae');
    const ufSelect = document.getElementById('search-uf');
    const municipioSelect = document.getElementById('search-municipio');
    const municipioDisplayInput = document.getElementById('search-municipio-display');
    const defaultMunicipioOptionLabel = 'Todos os municipios';
    let municipioRequestId = 0;
    let lastMunicipioUf = '';
    const normalizeMunicipioValue = function (value) {
        if (!value) {
            return '';
        }

        let result = String(value);
        if (typeof result.normalize === 'function') {
            result = result.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        result = result.replace(/[^A-Za-z0-9\s-]/g, '');
        result = result.replace(/\s+/g, ' ');

        return result.trim().toUpperCase();
    };

    const resetMunicipioSelect = function (shouldDisable) {
        if (!municipioSelect) {
            return;
        }

        municipioSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = defaultMunicipioOptionLabel;
        municipioSelect.appendChild(option);
        municipioSelect.disabled = Boolean(shouldDisable);
        municipioSelect.classList.remove('opacity-60');
        municipioSelect.dataset.loadedUf = '';
        lastMunicipioUf = '';

        if (municipioDisplayInput) {
            municipioDisplayInput.value = '';
        }
    };

    const syncMunicipioDisplay = function () {
        if (!municipioSelect || !municipioDisplayInput) {
            return;
        }

        const option = municipioSelect.options[municipioSelect.selectedIndex];
        if (option && option.value !== '') {
            municipioDisplayInput.value = option.dataset.display || option.textContent || '';
        } else {
            municipioDisplayInput.value = '';
        }
    };

    const populateMunicipios = function (lista, selectedValueNormalized, selectedDisplay) {
        if (!municipioSelect) {
            return;
        }

        const fragment = document.createDocumentFragment();
        const baseOption = document.createElement('option');
        baseOption.value = '';
        baseOption.textContent = defaultMunicipioOptionLabel;
        fragment.appendChild(baseOption);

        const normalizedSelectedValue = selectedValueNormalized ? String(selectedValueNormalized).toUpperCase() : '';
        const normalizedSelectedDisplay = selectedDisplay ? normalizeMunicipioValue(selectedDisplay) : '';

        let selectionApplied = false;

        (Array.isArray(lista) ? lista : [])
            .filter(function (item) {
                return item && item.nome;
            })
            .sort(function (a, b) {
                return a.nome.localeCompare(b.nome, 'pt-BR');
            })
            .forEach(function (item) {
                const displayName = item.nome;
                const normalizedName = normalizeMunicipioValue(displayName);
                const option = document.createElement('option');
                option.value = normalizedName;
                option.textContent = displayName;
                option.dataset.display = displayName;

                if (!selectionApplied && normalizedSelectedValue && normalizedName === normalizedSelectedValue) {
                    option.selected = true;
                    selectionApplied = true;
                } else if (!selectionApplied && !normalizedSelectedValue && normalizedSelectedDisplay && normalizedName === normalizedSelectedDisplay) {
                    option.selected = true;
                    selectionApplied = true;
                }

                fragment.appendChild(option);
            });

        if (!selectionApplied && normalizedSelectedValue) {
            const fallbackOption = document.createElement('option');
            fallbackOption.value = normalizedSelectedValue;
            fallbackOption.textContent = selectedDisplay || normalizedSelectedValue;
            fallbackOption.dataset.display = selectedDisplay || normalizedSelectedValue;
            fallbackOption.selected = true;
            fragment.appendChild(fallbackOption);
        }

        municipioSelect.innerHTML = '';
        municipioSelect.appendChild(fragment);
        municipioSelect.disabled = false;
        syncMunicipioDisplay();
    };

    const loadMunicipios = async function (uf, selectedValue, selectedDisplay) {
        if (!municipioSelect) {
            return;
        }

        const resolvedUf = typeof uf === 'string' ? uf.trim().toUpperCase() : '';
        const normalizedSelectedValue = selectedValue ? normalizeMunicipioValue(selectedValue) : '';
        const selectedDisplayValue = selectedDisplay ? String(selectedDisplay) : '';

        if (!resolvedUf) {
            resetMunicipioSelect(true);
            return;
        }

        if (resolvedUf === lastMunicipioUf && municipioSelect.dataset.loadedUf === resolvedUf) {
            municipioSelect.disabled = false;
            if (normalizedSelectedValue) {
                municipioSelect.value = normalizedSelectedValue;
            } else {
                municipioSelect.value = '';
            }
            syncMunicipioDisplay();
            return;
        }

        municipioRequestId += 1;
        const currentId = municipioRequestId;

        municipioSelect.disabled = true;
        municipioSelect.classList.add('opacity-60');
        if (municipioDisplayInput) {
            municipioDisplayInput.value = '';
        }

        try {
            const response = await fetch('api/municipios.php?uf=' + encodeURIComponent(resolvedUf));
            if (!response.ok) {
                throw new Error('Resposta HTTP invalida');
            }

            const lista = await response.json();
            if (currentId !== municipioRequestId) {
                return;
            }

            lastMunicipioUf = resolvedUf;
            populateMunicipios(lista, normalizedSelectedValue, selectedDisplayValue);
            municipioSelect.dataset.loadedUf = resolvedUf;
        } catch (error) {
            console.error('Falha ao carregar municipios', error);
            resetMunicipioSelect(false);
            if (normalizedSelectedValue) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = normalizedSelectedValue;
                fallbackOption.textContent = selectedDisplayValue || normalizedSelectedValue;
                fallbackOption.dataset.display = selectedDisplayValue || normalizedSelectedValue;
                fallbackOption.selected = true;
                municipioSelect.appendChild(fallbackOption);
            }
            syncMunicipioDisplay();
        } finally {
            municipioSelect.classList.remove('opacity-60');
        }
    };

    if (municipioSelect) {
        const initialShouldDisable = !(ufSelect && ufSelect.value);
        resetMunicipioSelect(initialShouldDisable);

        if (ufSelect && ufSelect.value) {
            const initialMunicipioValue = municipioSelect.dataset.initialMunicipio || '';
            const initialMunicipioDisplay = municipioSelect.dataset.initialDisplay || '';
            loadMunicipios(ufSelect.value, initialMunicipioValue, initialMunicipioDisplay).finally(function () {
                municipioSelect.dataset.initialMunicipio = '';
                municipioSelect.dataset.initialDisplay = '';
            });
        }
    }

    if (ufSelect && municipioSelect) {
        ufSelect.addEventListener('change', function () {
            municipioSelect.dataset.initialMunicipio = '';
            municipioSelect.dataset.initialDisplay = '';
            loadMunicipios(ufSelect.value, '', '');
        });
    }

    if (municipioSelect) {
        municipioSelect.addEventListener('change', function () {
            syncMunicipioDisplay();
        });
    }

    if (searchForm && municipioSelect) {
        searchForm.addEventListener('submit', function () {
            syncMunicipioDisplay();
        });
    }

    if (cnaeInput) {
        cnaeInput.addEventListener('input', function () {
            const digits = this.value.replace(/\D/g, '').slice(0, 7);
            if (this.value !== digits) {
                this.value = digits;
            }
        });
    }

    const historyButtons = document.querySelectorAll('.history-apply');
    historyButtons.forEach(function (button) {
        button.addEventListener('click', async function () {
            if (!searchForm) {
                return;
            }

            const raw = button.getAttribute('data-filtros') || '{}';
            let filtros;

            try {
                filtros = JSON.parse(raw);
            } catch (error) {
                console.error('Erro ao processar filtros do historico', error);
                return;
            }

            if (typeof filtros !== 'object' || filtros === null) {
                return;
            }

            const setFieldValue = function (name, value) {
                const field = searchForm.querySelector('[name=\"' + name + '\"]');
                if (!field) {
                    return;
                }

                let resolved = '';
                if (Array.isArray(value)) {
                    resolved = value.join(', ');
                } else if (value !== undefined && value !== null) {
                    resolved = String(value);
                }
                field.value = resolved;
            };

            const setCheckbox = function (name, flag) {
                const checkbox = searchForm.querySelector('input[name=\"' + name + '\"]');
                if (checkbox) {
                    checkbox.checked = Boolean(flag);
                }
            };

            const applySituacao = function (value) {
                const select = document.getElementById('search-situacao');
                if (!select) {
                    return;
                }

                let resolved = Array.isArray(value) ? value[0] : value;
                resolved = resolved ? String(resolved).toUpperCase() : 'ATIVA';

                const matchingOption = Array.from(select.options).find(function (option) {
                    return option.value === resolved;
                });

                if (matchingOption) {
                    select.value = matchingOption.value;
                } else if (select.options.length > 0) {
                    select.selectedIndex = 0;
                }
            };

            const ufValor = Array.isArray(filtros.uf) ? (filtros.uf[0] ?? '') : (filtros.uf ?? '');
            const municipioValor = Array.isArray(filtros.municipio) ? (filtros.municipio[0] ?? '') : (filtros.municipio ?? '');
            const municipioDisplayValor = filtros.municipio_display ?? '';

            setFieldValue('cnae', filtros.cnae);
            setFieldValue('uf', ufValor);
            setFieldValue('municipio_display', municipioDisplayValor);
            await loadMunicipios(ufValor, municipioValor, municipioDisplayValor);
            if (municipioDisplayInput && municipioDisplayValor && municipioDisplayInput.value === '') {
                municipioDisplayInput.value = municipioDisplayValor;
            }
            setFieldValue('quantidade', filtros.quantidade);
            setFieldValue('pagina', filtros.pagina);
            setFieldValue('capital_social_minimo', filtros.capital_social_minimo);
            setFieldValue('capital_social_maximo', filtros.capital_social_maximo);
            setFieldValue('codigo_atividade_secundaria', filtros.codigo_atividade_secundaria);
            setFieldValue('codigo_natureza_juridica', filtros.codigo_natureza_juridica);
            setFieldValue('cep', filtros.cep);
            setFieldValue('cnpj', filtros.cnpj);
            setFieldValue('ddd', filtros.ddd);
            setFieldValue('data_abertura_inicio', filtros.data_abertura_inicio);
            setFieldValue('data_abertura_fim', filtros.data_abertura_fim);
            setFieldValue('data_abertura_ultimos_dias', filtros.data_abertura_ultimos_dias);
            setFieldValue('mei', filtros.mei);
            setFieldValue('simples', filtros.simples);

            setCheckbox('mei_excluir', filtros.mei_excluir);
            setCheckbox('simples_excluir', filtros.simples_excluir);
            setCheckbox('somente_celular', filtros.somente_celular);
            setCheckbox('somente_fixo', filtros.somente_fixo);
            setCheckbox('somente_matriz', filtros.somente_matriz);
            setCheckbox('somente_filial', filtros.somente_filial);
            setCheckbox('com_email', filtros.com_email);
            setCheckbox('com_telefone', filtros.com_telefone);
            setCheckbox('excluir_email_contab', filtros.excluir_email_contab);
            setCheckbox('excluir_empresas_visualizadas', filtros.excluir_empresas_visualizadas);
            setCheckbox('incluir_atividade_secundaria', filtros.incluir_atividade_secundaria);

            const situacaoValor = filtros.situacao !== undefined ? filtros.situacao : filtros.situacao_cadastral;
            applySituacao(situacaoValor);

            if (!municipioValor && municipioSelect) {
                municipioSelect.value = '';
                syncMunicipioDisplay();
            }

            if (typeof searchForm.requestSubmit === 'function') {
                searchForm.requestSubmit();
            } else {
                searchForm.submit();
            }
        });
    });

    const leadLinks = document.querySelectorAll('.lead-view-link');
    leadLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const proceed = confirm('Visualizar os detalhes deste lead consumira 1 credito. Deseja continuar?');
            if (proceed) {
                window.location.href = link.href;
            }
        });
    });

    document.querySelectorAll('.single-add-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const confirmation = confirm('Adicionar este lead a lista consumira 1 credito. Deseja continuar?');
            if (!confirmation) {
                event.preventDefault();
            }
        });
    });

    const bulkForm = document.getElementById('bulk-add-form');
    if (bulkForm) {
        const checkboxes = document.querySelectorAll('.bulk-lead-checkbox');
        const hiddenContainer = document.getElementById('bulk-hidden-container');
        const submitBtn = document.getElementById('bulk-submit-btn');
        const selectAll = document.getElementById('bulk-select-all');
        const listaSelect = document.getElementById('bulk-lista-select');
        const countDisplay = document.getElementById('bulk-count-display');

        const updateHidden = function () {
            if (hiddenContainer) {
                hiddenContainer.innerHTML = '';
            }

            let selected = 0;

            checkboxes.forEach(function (cb) {
                if (cb.checked) {
                    selected += 1;
                    if (hiddenContainer) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'lead_tokens[]';
                        input.value = cb.value;
                        hiddenContainer.appendChild(input);
                    }
                }
            });

            bulkForm.dataset.selectedCount = String(selected);

            if (countDisplay) {
                countDisplay.textContent = selected;
            }

            const listaSelecionada = listaSelect && listaSelect.value !== '';
            const habilitado = selected > 0 && listaSelecionada;

            if (submitBtn) {
                submitBtn.disabled = !habilitado;
                submitBtn.classList.toggle('opacity-50', !habilitado);
                submitBtn.classList.toggle('cursor-not-allowed', !habilitado);
            }
        };

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', updateHidden);
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                const marcado = selectAll.checked;
                checkboxes.forEach(function (cb) {
                    cb.checked = marcado;
                });
                updateHidden();
            });
        }

        if (listaSelect) {
            listaSelect.addEventListener('change', updateHidden);
        }

        bulkForm.addEventListener('submit', function (event) {
            updateHidden();

            if (submitBtn && submitBtn.disabled) {
                event.preventDefault();
                return;
            }

            const selecionados = parseInt(bulkForm.dataset.selectedCount || '0', 10);
            if (selecionados > 0) {
                const mensagem = 'Adicionar ' + selecionados + ' lead' + (selecionados > 1 ? 's' : '') + ' consumira ' + selecionados + ' credito' + (selecionados > 1 ? 's' : '') + '. Deseja continuar?';
                if (!confirm(mensagem)) {
                    event.preventDefault();
                }
            }
        });

        updateHidden();
    }

});


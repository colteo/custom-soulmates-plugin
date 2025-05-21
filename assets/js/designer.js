(function ($)
{
    'use strict';

    $(document).ready(function ()
    {
        // Verifica se siamo nella pagina di un prodotto personalizzato
        if ($('.custom-tshirt-designer').length === 0)
        {
            return;
        }

        console.log('Custom T-Shirt Designer inizializzato');

        // Contatore per le varianti
        var variantCount = 1; // Inizia da 1 perché la prima variante è già presente

        // Gestione upload immagine
        $('#custom_tshirt_image').on('change', function ()
        {
            var file = this.files[0];
            if (file)
            {
                // Mostra anteprima
                var reader = new FileReader();
                reader.onload = function (e)
                {
                    $('#image_preview').html('<img src="' + e.target.result + '" alt="Anteprima">');
                };
                reader.readAsDataURL(file);

                // Carica file tramite AJAX
                var form_data = new FormData();
                form_data.append('file', file);
                form_data.append('action', 'custom_tshirt_upload_image');
                form_data.append('security', customTshirtData.nonce);
                form_data.append('product_id', customTshirtData.productId);

                $.ajax({
                    url: customTshirtData.ajaxUrl,
                    type: 'POST',
                    contentType: false,
                    processData: false,
                    data: form_data,
                    success: function (response)
                    {
                        console.log('Upload response:', response);
                        if (response.success)
                        {
                            $('#custom_tshirt_image_path').val(response.data.path);
                        } else
                        {
                            alert(response.data.message);
                            $('#image_preview').empty();
                        }
                    },
                    error: function (xhr, status, error)
                    {
                        console.error('Upload error:', error);
                        alert('Si è verificato un errore durante il caricamento. Riprova.');
                        $('#image_preview').empty();
                    }
                });
            }
        });

        // Aggiornamento prezzo e descrizione in base al tipo
        $(document).on('change', 'select[name="custom_tshirt_type"], select[name^="variant_type_"]', function ()
        {
            var selectedOption = $(this).find('option:selected');
            var typePrice = selectedOption.data('price');
            var typeDescription = selectedOption.data('description');
            var variantContainer = $(this).closest('.custom-tshirt-variant');

            // Aggiorna la descrizione se presente
            if (variantContainer.find('.type-description').length > 0)
            {
                if (typeDescription)
                {
                    variantContainer.find('.type-description').text(typeDescription).show();
                } else
                {
                    variantContainer.find('.type-description').text('').hide();
                }
            }
        });

        // Gestione selettore colori visuale
        $(document).on('click', '.color-option', function ()
        {
            var variantContainer = $(this).closest('.custom-tshirt-variant');
            variantContainer.find('.color-option').removeClass('selected');
            $(this).addClass('selected');
            var selectedColor = $(this).data('color');
            variantContainer.find('input[name="custom_tshirt_color"], input[name^="variant_color_"]').val(selectedColor);
        });

        // Toggle guida alle taglie
        $('.toggle-size-guide').on('click', function (e)
        {
            e.preventDefault();
            $('.size-guide-content').slideToggle();
        });

        // Gestione varianti multiple
        $('#add_another_variant').on('click', function ()
        {
            // Controlla che l'immagine sia stata caricata
            if ($('#custom_tshirt_image_path').val() === '')
            {
                alert('Devi prima caricare un\'immagine');
                return;
            }

            variantCount++;

            // Clona il contenuto delle select dalla prima variante
            var typeOptions = '';
            $('#custom_tshirt_type option').each(function ()
            {
                if ($(this).val() !== '')
                {
                    var selected = $(this).is(':selected') ? ' selected' : '';
                    typeOptions += '<option value="' + $(this).val() + '" data-price="' + $(this).data('price') + '" data-description="' + $(this).data('description') + '"' + selected + '>' + $(this).text() + '</option>';
                }
            });

            var colorOptions = '';
            if ($('#custom_tshirt_color').is('select'))
            {
                $('#custom_tshirt_color option').each(function ()
                {
                    if ($(this).val() !== '')
                    {
                        var selected = $(this).is(':selected') ? ' selected' : '';
                        colorOptions += '<option value="' + $(this).val() + '"' + selected + '>' + $(this).text() + '</option>';
                    }
                });
            }

            var sizeOptions = '';
            $('#custom_tshirt_size option').each(function ()
            {
                if ($(this).val() !== '')
                {
                    var selected = $(this).is(':selected') ? ' selected' : '';
                    sizeOptions += '<option value="' + $(this).val() + '"' + selected + '>' + $(this).text() + '</option>';
                }
            });

            // Costruisci HTML per la nuova variante
            var variantHtml = '<div class="custom-tshirt-variant" id="variant_' + variantCount + '">' +
                '<div class="variant-header">' +
                '<h4>' + 'Variante #' + variantCount + '</h4>' +
                '<button type="button" class="button remove-variant" data-variant="' + variantCount + '">Rimuovi</button>' +
                '</div>' +
                '<div class="variant-options">' +
                '<div class="variant-type">' +
                '<label>Tipo:</label>' +
                '<select name="variant_type_' + variantCount + '" class="variant-type-select" required>' +
                typeOptions +
                '</select>' +
                '</div>';

            // Aggiungi selettore colore appropriato
            if ($('#custom_tshirt_color').is('select'))
            {
                variantHtml += '<div class="variant-color">' +
                    '<label>Colore:</label>' +
                    '<select name="variant_color_' + variantCount + '" required>' +
                    colorOptions +
                    '</select>' +
                    '</div>';
            } else
            {
                // Clone visual color selector
                var colorSelector = $('.main-variant .color-selector').clone();
                colorSelector.find('.color-option').removeClass('selected');

                variantHtml += '<div class="variant-color">' +
                    '<label>Colore:</label>' +
                    '<div class="color-selector">' + colorSelector.html() + '</div>' +
                    '<input type="hidden" name="variant_color_' + variantCount + '" required>' +
                    '</div>';
            }

            variantHtml += '<div class="variant-size">' +
                '<label>Taglia:</label>' +
                '<select name="variant_size_' + variantCount + '" required>' +
                sizeOptions +
                '</select>' +
                '</div>' +
                '<div class="variant-quantity">' +
                '<label>Quantità:</label>' +
                '<input type="number" name="variant_quantity_' + variantCount + '" value="1" min="1" step="1" required>' +
                '</div>' +
                '</div>' +
                '</div>';

            $('#custom_tshirt_variants_container').append(variantHtml);
        });

        // Rimuovi variante (delegata per elementi dinamici)
        $(document).on('click', '.remove-variant', function ()
        {
            var variantId = $(this).data('variant');
            $('#variant_' + variantId).remove();

            // Rinumera le varianti rimanenti
            $('.custom-tshirt-variant:not(.main-variant)').each(function (index)
            {
                $(this).find('h4').text('Variante #' + (index + 2)); // +2 perché la prima è già presente e parte da 1
            });
        });

        // Sostituisci il comportamento del pulsante "Aggiungi al carrello"
        $('form.cart').on('submit', function (e)
        {
            e.preventDefault();

            // Verifica che l'immagine sia stata caricata
            if ($('#custom_tshirt_image_path').val() === '')
            {
                alert('Devi caricare un\'immagine per la tua maglietta personalizzata');
                return false;
            }

            // Raccogli i dati della variante principale
            var mainVariant = {
                product_id: customTshirtData.productId,
                image_path: $('#custom_tshirt_image_path').val(),
                type: $('#custom_tshirt_type').val(),
                color: $('#custom_tshirt_color').val(),
                size: $('#custom_tshirt_size').val(),
                quantity: $('.main-variant .qty').val()
            };

            // Raccogli i dati di tutte le varianti
            var variants = [mainVariant];

            $('.custom-tshirt-variant:not(.main-variant)').each(function ()
            {
                var variantId = $(this).attr('id').replace('variant_', '');
                var variantColor;

                // Ottieni il colore (diverso se è visuale o select)
                if ($(this).find('select[name="variant_color_' + variantId + '"]').length > 0)
                {
                    variantColor = $(this).find('select[name="variant_color_' + variantId + '"]').val();
                } else
                {
                    variantColor = $(this).find('input[name="variant_color_' + variantId + '"]').val();
                }

                variants.push({
                    product_id: customTshirtData.productId,
                    image_path: $('#custom_tshirt_image_path').val(),
                    type: $(this).find('select[name="variant_type_' + variantId + '"]').val(),
                    color: variantColor,
                    size: $(this).find('select[name="variant_size_' + variantId + '"]').val(),
                    quantity: $(this).find('input[name="variant_quantity_' + variantId + '"]').val()
                });
            });

            // Invia i dati tramite AJAX
            $.ajax({
                type: 'POST',
                url: customTshirtData.ajaxUrl,
                data: {
                    action: 'add_multiple_tshirt_variants',
                    variants: variants,
                    security: customTshirtData.nonce
                },
                success: function (response)
                {
                    if (response.success)
                    {
                        window.location.href = response.data.redirect || woocommerce_params.cart_url;
                    } else
                    {
                        alert(response.data.message || 'Si è verificato un errore durante l\'aggiunta al carrello.');
                    }
                },
                error: function ()
                {
                    alert('Si è verificato un errore durante la comunicazione con il server.');
                }
            });
        });

        // Debugging
        console.log('Custom T-Shirt Designer fully initialized');
    });
})(jQuery);
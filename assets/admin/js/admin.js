(function ($)
{
    'use strict';

    $(document).ready(function ()
    {
        // Mostra/nascondi campi in base al tipo di prodotto
        function toggleCustomTshirtFields()
        {
            var productType = $('#product-type').val();

            if (productType === 'custom_tshirt')
            {
                // Nascondi campi non necessari
                $('.general_options, .inventory_options').show();
                $('._regular_price_field, ._sale_price_field').hide();

                // Mostra avviso prezzi
                if (!$('.custom-tshirt-price-notice').length)
                {
                    $('.pricing').prepend('<div class="custom-tshirt-price-notice notice notice-info inline"><p>I prezzi per le magliette personalizzate vengono configurati nella scheda "Opzioni Maglietta".</p></div>');
                }
            } else
            {
                // Rimuovi avviso prezzi se esiste
                $('.custom-tshirt-price-notice').remove();
            }
        }

        // Inizializza al caricamento e al cambio tipo
        $('#product-type').on('change', toggleCustomTshirtFields);
        toggleCustomTshirtFields();

        // Gestione aggiunta colori con invio
        $('#new_color').on('keypress', function (e)
        {
            if (e.which === 13)
            { // Enter key
                e.preventDefault();

                var colorValue = $(this).val().trim();
                if (colorValue)
                {
                    // Verifica se esistono già altri tag colore
                    var existingTags = $('.color-tags-container .color-tag').length;

                    // Aggiungi il tag colore COME TAG SEPARATO
                    $('.color-tags-container').append('<span class="color-tag">' + colorValue + '<a class="remove-tag">×</a></span>');

                    // Aggiorna il campo nascosto
                    updateHiddenField('_custom_tshirt_colors', '.color-tag');

                    // Pulisci l'input
                    $(this).val('');
                }
            }
        });

        // Gestione aggiunta taglie con invio
        $('#new_size').on('keypress', function (e)
        {
            if (e.which === 13)
            { // Enter key
                e.preventDefault();

                var sizeValue = $(this).val().trim();
                if (sizeValue)
                {
                    // Aggiungi il tag taglia
                    $('.size-tags-container').append('<span class="size-tag">' + sizeValue + '<a class="remove-tag">×</a></span>');

                    // Aggiorna il campo nascosto
                    updateHiddenField('_custom_tshirt_sizes', '.size-tag');

                    // Pulisci l'input
                    $(this).val('');
                }
            }
        });

        // Gestione rimozione tag (delegata)
        $(document).on('click', '.remove-tag', function ()
        {
            var parentTag = $(this).parent();
            var isColorTag = parentTag.hasClass('color-tag');
            var isSizeTag = parentTag.hasClass('size-tag');

            // Rimuovi il tag
            parentTag.remove();

            // Aggiorna il campo nascosto appropriato
            if (isColorTag)
            {
                updateHiddenField('_custom_tshirt_colors', '.color-tag');
            } else if (isSizeTag)
            {
                updateHiddenField('_custom_tshirt_sizes', '.size-tag');
            }
        });

        // Funzione per aggiornare i campi nascosti - MODIFICATA
        function updateHiddenField(fieldId, tagSelector)
        {
            var values = [];

            // Raccoglie OGNI tag come elemento separato
            $(tagSelector).each(function ()
            {
                // Estrai solo il testo del tag, eliminando il bottone di rimozione "×"
                var tagText = $(this).clone().children().remove().end().text().trim();
                if (tagText)
                {
                    values.push(tagText);
                }
            });

            // Aggiorna il campo nascosto usando newline come separatore
            $('#' + fieldId).val(values.join('\n'));
        }

        // Gestione tipi di magliette
        var typeCounter = $('.tshirt-type-item').length;

        // Aggiunta nuovo tipo
        $('.add-tshirt-type-button').on('click', function ()
        {
            typeCounter++;
            var template = $('#tshirt-type-template').html();
            var newIndex = typeCounter - 1;
            var newId = 'type_' + Date.now();

            template = template.replace(/\{\{index\}\}/g, newIndex)
                .replace(/\{\{id\}\}/g, newId)
                .replace(/\{\{name\}\}/g, '')
                .replace(/\{\{price\}\}/g, '')
                .replace(/\{\{description\}\}/g, '')
                .replace(/\{\{image\}\}/g, '');

            $('.tshirt-types-container').append(template);
            updateTshirtTypes();
        });

        // Rimozione tipo
        $(document).on('click', '.remove-type', function (e)
        {
            e.preventDefault();

            if (confirm('Sei sicuro di voler rimuovere questo tipo di maglietta?'))
            {
                $(this).closest('.tshirt-type-item').remove();
                updateTshirtTypes();
            }
        });

        // Espandi/comprimi tipo
        $(document).on('click', '.collapse-type', function (e)
        {
            e.preventDefault();
            $(this).closest('.tshirt-type-item').find('.tshirt-type-content').slideToggle();
        });

        // Aggiornamento nome visualizzato
        $(document).on('input', '.type-name-field', function ()
        {
            var nameValue = $(this).val();
            $(this).closest('.tshirt-type-item').find('.type-name-display').text(nameValue);
            updateTshirtTypes();
        });

        // Upload immagine
        $(document).on('click', '.upload-type-image', function (e)
        {
            e.preventDefault();

            var button = $(this);
            var imageContainer = button.closest('.tshirt-type-image-container');
            var imageField = imageContainer.find('.tshirt-type-image-field');

            // Crea il frame media
            var frame = wp.media({
                title: 'Seleziona o carica un\'immagine',
                button: {
                    text: 'Usa questa immagine'
                },
                multiple: false
            });

            // Quando un'immagine viene selezionata
            frame.on('select', function ()
            {
                var attachment = frame.state().get('selection').first().toJSON();

                // Aggiorna il campo con l'URL dell'immagine
                imageField.val(attachment.url);

                // Mostra l'anteprima
                if (imageContainer.find('.tshirt-type-thumbnail').length === 0)
                {
                    imageContainer.prepend('<img src="' + attachment.url + '" class="tshirt-type-thumbnail">');
                    button.after('<button type="button" class="button remove-type-image">Rimuovi immagine</button>');
                } else
                {
                    imageContainer.find('.tshirt-type-thumbnail').attr('src', attachment.url);
                }

                updateTshirtTypes();
            });

            // Apri il selettore
            frame.open();
        });

        // Rimozione immagine
        $(document).on('click', '.remove-type-image', function (e)
        {
            e.preventDefault();

            var imageContainer = $(this).closest('.tshirt-type-image-container');
            imageContainer.find('.tshirt-type-thumbnail').remove();
            imageContainer.find('.tshirt-type-image-field').val('');
            $(this).remove();

            updateTshirtTypes();
        });

        // Funzione per aggiornare il campo hidden con tutti i tipi
        function updateTshirtTypes()
        {
            var types = [];

            $('.tshirt-type-item').each(function (index)
            {
                var $item = $(this);

                types.push({
                    id: $item.data('type-id'),
                    name: $item.find('.type-name-field').val(),
                    price: $item.find('input[type="number"]').val(),
                    description: $item.find('textarea').val(),
                    image: $item.find('.tshirt-type-image-field').val()
                });
            });

            $('#_custom_tshirt_types').val(JSON.stringify(types));
        }
    });
})(jQuery);
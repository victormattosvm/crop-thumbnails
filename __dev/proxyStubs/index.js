/**
 * this is for testing the system directly in the browser
 */
const STUBS = [
  { 
    url: '/fake-ajax-url?action=cpt_cropdata&imageId=123&posttype=', 
    method: 'GET', 
    content:
        {
        options: {
            "hide_post_type": { "custom_css": "1", "customize_changeset": "1", "wpcf7_contact_form": "1" },
            "hide_size": {
                "post": { "thumbnail": "1", "mitarbeiter": "1" },
                "page": { "thumbnail": "1", "mitarbeiter": "1" },
                "mitarbeiter": { "thumbnail": "1", "post-thumbnail": "1", "small-thumb": "1" },
                "angebote": { "mitarbeiter": "1" }
            }
        },
        sourceImageId: 123,
        sourceImage: {
            "full": {
                "url": "/testimages/testimage.jpeg",
                "width": 1920, "height": 1280, "gcd": "640", "ratio": 1.5, "printRatio": "3:2", "image_size": "full"
            },
            "large": {
                "url": "/testimages/testimage-1024x683.jpeg",
                "width": 1024, "height": 683, "gcd": "1", "ratio": 1.499267935578331, "printRatio": "1024:683", "image_size": "large"
            },
            "medium_large": {
                "url": "/testimages/testimage-768x512.jpeg",
                "width": 768, "height": 512, "gcd": "256", "ratio": 1.5, "printRatio": "3:2", "image_size": "medium_large"
            }
        },
        sourceImageMeta: { "aperture": "0", "credit": "", "camera": "", "caption": "", "created_timestamp": "0", "copyright": "", "focal_length": "0", "iso": "0", "shutter_speed": "0", "title": "", "orientation": "0", "keywords": [] },
        postTypeFilter: null,
        imageSizes: [
            {
                "name": "thumbnail", "nameLabel": "thumbnail",
                "url": "/testimages/testimage-200x200.jpeg",
                "width": 200, "height": 200, "gcd": "200", "ratio": 1, "printRatio": "1:1", "hideByPostType": false, "crop": true
            },
            {
                "name": "post-thumbnail", "nameLabel": "post-thumbnail",
                "url": "/testimages/testimage-625x275.jpeg",
                "width": 625, "height": 275, "gcd": "25", "ratio": 2.272727272727273, "printRatio": "25:11", "hideByPostType": false, "crop": true
            },
            {
                "name": "small-thumb", "nameLabel": "small-thumb",
                "url": "/testimages/testimage-250x140.jpeg",
                "width": 250, "height": 140, "gcd": "10", "ratio": 1.7857142857142858, "printRatio": "25:14", "hideByPostType": false, "crop": true
            },
            {
                "name": "medium-thumb", "nameLabel": "medium-thumb",
                "url": "/testimages/testimage-500x280.jpeg",
                "width": 500, "height": 280, "gcd": "20", "ratio": 1.7857142857142858, "printRatio": "25:14", "hideByPostType": false, "crop": true
            },
            {
                "name": "mitarbeiter", "nameLabel": "mitarbeiter",
                "url": "/testimages/testimage-450x300.jpeg",
                "width": 450, "height": 300, "gcd": "150", "ratio": 1.5, "printRatio": "3:2", "hideByPostType": false, "crop": true
            },
        ],
        lang: {
            "warningOriginalToSmall": "Warung: das Original-Bild ist zu klein um es für diese Thumbnail-Größe in guter Qualität zuzuschneiden.",
            "cropDisabled": "Das Zuschneiden ist für diesen Eintragstyp deaktiviert.",
            "waiting": "Bitte warten Sie bis die Bilder zugeschnitten wurden.",
            "rawImage": "Original-Bild",
            "pixel": "Pixel",
            "instructions_overlay_text": "Wählen Sie eine Bildgröße aus.",
            "instructions_header": "Schnell-Anleitung",
            "instructions_step_1": "Schritt 1: Wählen Sie ein Bild aus der Liste.",
            "instructions_step_2": "Schritt 2: Ändern Sie den Auswahlrahmen im obigen Bild.",
            "instructions_step_3": "Schritt 3: Klicken Sie auf \"Zuschnitt übernehmen\".",
            "label_crop": "Zuschnitt übernehmen",
            "label_same_ratio_mode": "Bilder mit gleichem Seitenverhältnis",
            "label_same_ratio_mode_nothing": "nichts tun",
            "label_same_ratio_mode_select": "gemeinsam auswählen",
            "label_same_ratio_mode_group": "gruppieren",
            "label_deselect_all": "nichts auswählen",
            "label_large_handles": "große Kontrollflächen verwenden",
            "dimensions": "Größe:",
            "ratio": "Seitenverhältnis:",
            "cropped": "zugeschnitten",
            "lowResWarning": "Original-Bild ist zu klein für guten Bildzuschnitt!",
            "notYetCropped": "Wurde bisher nicht von WordPress zugeschnitten.",
            "message_image_orientation": "Dieses Bild nutzt eine Bild-Rotation in seinen EXIF-Metadaten. Beachten Sie, dass das zu gedrehten oder gespiegelten Bildern beim Safari-Browser (IPad, IPhone) führen kann.",
            "script_connection_error": "Fehler beim Verbindungsaufbau zum Server.",
            "noPermission": "Es ist dir nicht gestattet, die Miniaturansichten zuzuschneiden.",
            "infoNoImageSizesAvailable": "Keine Bildgrößen für den Bild-Zuschnitt verfügbar.",
            "headline_selected_image_sizes": "Ausgewählte Bildgrößen"
        },
        nonce: "abc123",
        hiddenOnPostType: false
    }
},
];


export default (proxyRes, req, res) => {
  const matchingStub = STUBS.find(stub => req.url.includes(stub.url) && req.method === stub.method);
  if(matchingStub) {
    res.statusCode = matchingStub.statusCode ? matchingStub.statusCode : 201;//custom statusCode
    console.log('send stub data on', req.method, req.url, res.statusCode);
    res.writeHead(res.statusCode, { 'Content-Type': 'application/json' });
    res.write.call(res, JSON.stringify( matchingStub.content ));
    res.end();
  } else {
    res.writeHead(res.statusCode, { 'Content-Type': 'application/json' });
    res.write.call(res, JSON.stringify( { 'error' : 'stub do not found an target' } ));
    console.log('stub do not found an target');
    res.end();
  }
}
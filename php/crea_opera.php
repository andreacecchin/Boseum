<?php

require_once 'DBAccess.php';
require_once 'ImageProcessor.php';
require_once 'utils.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
setlocale(LC_ALL, 'it_IT');

session_start();
$isLoggedIn = isset($_SESSION['logged_id']);
$loginOrProfileTitle = "";
if (!$isLoggedIn || !isset($_POST['id_artist']) || ($_SESSION['logged_id'] != $_POST['id_artist'] && !$_SESSION['is_admin'])) {
    if (!isset($_POST['save_new_artwork']) && !isset($_POST['update_artwork'])) {
        header('Location: index.php');
        exit();
    }
} else {
    $loginOrProfileTitle = "<a href=\"artista.php?id=" . $_SESSION['logged_id'] . "\"><span lang=\"en\">Account</span></a>";
}

$connection = new DB\DBAccess();
if (!$connection->openDBConnection()) {
    header("location: 500.php");
    exit();
}

$prevPage = "<li>
<a href=\"artista.php?id=" . $_SESSION['logged_id'] . "\">Account</a>
</li>";
$pageTitle = "";
$submitButton = "";
$mainImageContainer = "";
$additionalImagesContainer = "";
$keepMainImage = "";
$keepAdditionalImages = "";
$title = "";
$description = "";
$labelsContainer = "";
$startDate = "";
$endDate = "";
$height = "";
$width = "";
$depth = "";

$labelsArtwork = array();
$errorCreateArtwork = "";
$errorModifyArtwork = "";
$errorMessage = "";

$labels = $connection->getLabels();

if (isset($_POST['save_new_artwork'])) {

    $imagesDir = "../uploads/artworks/";

    $mainImage = null;
    if ($_FILES["main_image"] && sizeof($_FILES["main_image"]) > 0) {
        $mainImage = ImageProcessor::processImage($_FILES["main_image"]);
    }
    $additionalImagesPath = array();
    if ($_FILES["additional_images"] && sizeof($_FILES["additional_images"]) > 0) {
        $additionalImagesPath = ImageProcessor::processImages($_FILES["additional_images"]);
    }

    $idArtist = $_SESSION['logged_id'];
    $title = isset($_POST['title']) ? Sanitizer::sanitize($_POST['title']) : '';
    $description = isset($_POST['description']) ? Sanitizer::sanitize($_POST['description']) : '';
    $startDate = isset($_POST['start_date']) ? Sanitizer::sanitize($_POST['start_date']) : '';
    $endDate = isset($_POST['end_date']) ? Sanitizer::sanitize($_POST['end_date']) : '';
    $height = isset($_POST['height']) ? Sanitizer::sanitize($_POST['height']) : '';
    $width = isset($_POST['width']) ? Sanitizer::sanitize($_POST['width']) : '';
    $depth = isset($_POST['depth']) ? Sanitizer::sanitize($_POST['depth']) : '';

    foreach ($labels as $label) {
        $labelName = str_replace(" ", "", strtolower($label['label']));
        if (isset($_POST[$labelName])) array_push($labelsArtwork, $label['label']);
    }

    if (empty($title) || empty($mainImage) || empty($description)) {
        $errorCreateArtwork = "Parametri non sufficienti";
    } else {

        $addArtwork = $connection->insertNewArtwork($title, $mainImage, $description, $height, $width, $depth, $startDate, $endDate, $idArtist, $additionalImagesPath, $labelsArtwork);

        if (!$addArtwork) {
            $errorCreateArtwork = "Errore nella creazione dell'opera";
        } else {
            $connection->closeConnection();
            header("location: opera.php?id=" . $addArtwork);
            exit();
        }
    }
} else if (isset($_POST['update_artwork'])) {
    $imagesDir = "../uploads/artworks/";
    $idArtwork = $_POST['id_artwork'];

    $mainImage = null;
    if (!isset($_POST['disable_main_image'])) {
        if (isset($_FILES["main_image"]) && $_FILES["main_image"]['size'] !== 0) {
            $mainImage = ImageProcessor::processImage($_FILES["main_image"]);
            $artworkPreview = $connection->getArtworkPreview($idArtwork);
            if ($artworkPreview && sizeof($artworkPreview) > 0) {
                foreach ($artworkPreview as $artworkPreview) {
                    ImageProcessor::deleteImage($artworkPreview['main_image']);
                }
            }
        }
    }

    $additionalImagesPath = null;
    if (!isset($_POST['disable_additional_images'])) {
        if (isset($_FILES["additional_images"]) && $_FILES["additional_images"]['size'] !== 0) {
            $additionalImagesPath = ImageProcessor::processImages($_FILES["additional_images"]);
            $additionalImages = $connection->getArtworkAdditionalImages($idArtwork);
            if ($additionalImages && sizeof($additionalImages) > 0) {
                foreach ($additionalImages as $additionalImage) {
                    ImageProcessor::deleteImage($additionalImage['image']);
                }
            }
        }
    } else {
        $additionalImagesPath = array();
    }

    $title = isset($_POST['title']) ? Sanitizer::sanitize($_POST['title']) : '';
    $description = isset($_POST['description']) ? Sanitizer::sanitize($_POST['description']) : '';
    $startDate = isset($_POST['start_date']) ? Sanitizer::sanitize($_POST['start_date']) : '';
    $endDate = isset($_POST['end_date']) ? Sanitizer::sanitize($_POST['end_date']) : '';
    $height = isset($_POST['height']) ? Sanitizer::sanitize($_POST['height']) : '';
    $width = isset($_POST['width']) ? Sanitizer::sanitize($_POST['width']) : '';
    $depth = isset($_POST['depth']) ? Sanitizer::sanitize($_POST['depth']) : '';
    $labelsArtwork = array();

    foreach ($labels as $label) {
        $labelName = str_replace(" ", "", strtolower($label['label']));
        if (isset($_POST[$labelName])) array_push($labelsArtwork, $label['label']);
    }

    if (empty($title) || empty($description)) {
        $errorModifyArtwork = "Parametri non sufficienti";
    } else {
        $modifiedArtwork = $connection->modifyArtwork($idArtwork, $title, $mainImage, $description, $height, $width, $depth, $startDate, $endDate, $additionalImagesPath, $labelsArtwork);

        if (!$modifiedArtwork) {
            $errorModifyArtwork = "Errore nell'aggiornamento dell'opera";
        } else {
            $connection->closeConnection();
            header("location: opera.php?id=" . $modifiedArtwork);
            exit();
        }
    }
}

if (isset($_POST["create_artwork"]) || $errorCreateArtwork != "") {
    if ($_SESSION['is_admin']) {
        header('Location: admin.php');
        exit();
    } else {
        $errorMessage = "<p class=\"error_message\"><em>" . $errorCreateArtwork . "</em></p>";

        $pageTitle = "Crea opera";
        $submitButton = "<div class=\"form_button\">
                            <button
                                type=\"submit\"
                                name=\"save_new_artwork\"
                                class=\"btn-primary\">
                                Crea opera
                            </button>
                        </div>";

        if ($labels && sizeof($labels) > 0) {
            $labelsContainer = "<ul id=\"labels_list\">";
            foreach ($labels as $label) {
                $labelName = str_replace(" ", "", strtolower($label['label']));
                $isChecked = false;
                if ($errorCreateArtwork && $labelsArtwork) {
                    $isChecked = in_array($label['label'], $artworkLabels);
                }
                $labelsContainer .= "
                <li>
                    <input
                        type=\"checkbox\"
                        class=\"label_checkbox\"
                        id=\"" . $labelName . "\"
                        value=\"" . $label['label'] . "\"
                        name=\"" . $labelName . "\"";
                if ($isChecked) {
                    $labelsContainer .= " checked";
                }
                $labelsContainer .= ">
                    <label for=\"" . $labelName . "\">" . ucfirst($label['label']) . "</label>
                </li>";
            }
            $labelsContainer .= "</ul>";
        }
    }
} else if (isset($_POST["modify_artwork"]) || $errorModifyArtwork != "") {
    $errorMessage = "<p class=\"error_message\"><em>" . $errorModifyArtwork . "</em></p>";

    $pageTitle = "Modifica opera";
    $idArtwork = $_POST['id_artwork'];
    $submitButton = "<input type=\"hidden\" name=\"id_artwork\" value=\"$idArtwork\">
                    <div class=\"form_button\">
                        <button
                            type=\"submit\"
                            name=\"update_artwork\"
                            class=\"btn-primary\">
                            Aggiorna opera
                        </button>
                    </div>";

    $keepMainImage = "<div class=\"disable_checkbox\">
                            <label for=\"disable_main_image\"
                                >Mantieni l'immagine principale
                                precedente</label
                            >
                            <input
                                type=\"checkbox\"
                                id=\"disable_main_image\"
                                name=\"keep_main_image\"
                                />
                        </div>";
    $keepAdditionalImages = "<div class=\"disable_checkbox\">
                                <label for=\"disable_additional_images\"
                                    >Mantieni le immagini di dettaglio
                                    precedenti</label
                                >
                                <input
                                    type=\"checkbox\"
                                    id=\"disable_additional_images\"
                                    name=\"keep_additional_images\"
                                    />
                            </div>";

    $infoArtworkArtist = $connection->getArtwork($idArtwork);
    $artworkLabels = $connection->getArtworkLabels($idArtwork);
    $additionalImages = $connection->getArtworkAdditionalImages($idArtwork);

    if ($infoArtworkArtist and sizeof($infoArtworkArtist) > 0) {
        $title = $infoArtworkArtist[0]['title'];
        $description = $infoArtworkArtist[0]['description'];
        $startDate = $infoArtworkArtist[0]['start_date'];
        $endDate = $infoArtworkArtist[0]['end_date'];
        $height = $infoArtworkArtist[0]['height'];
        $width = $infoArtworkArtist[0]['width'];
        $depth = $infoArtworkArtist[0]['length'];
    } else {
        echo "Errore, l'opera non esiste";
    }

    $prevPage = "<li>
                <a href=\"opera.php?id=" . $idArtwork . "\">" . $title . "</a>
            </li>";

    if ($labels && sizeof($labels) > 0) {
        $labelsContainer = "<ul id=\"labels_list\">";
        foreach ($labels as $label) {
            $labelName = str_replace(" ", "", strtolower($label['label']));
            $isChecked = in_array(['label' => $label['label']], $artworkLabels);
            $labelsContainer .= "
            <li>
                <input
                    type=\"checkbox\"
                    class=\"label_checkbox\"
                    id=\"" . $labelName . "\"
                    value=\"" . $label['label'] . "\"
                    name=\"" . $labelName . "\"";

            if ($isChecked) {
                $labelsContainer .= " checked";
            }
            $labelsContainer .= ">
                <label for=\"" . $labelName . "\">" . ucfirst($label['label']) . "</label>
            </li>";
        }
        $labelsContainer .= "</ul>";
    }
} else {
    header('Location: index.php');
    exit();
}
$connection->closeConnection();

$creaOpera = file_get_contents("../templates/crea_opera.html");
$creaOpera = str_replace("{{login_or_profile_title}}", $loginOrProfileTitle, $creaOpera);
$creaOpera = str_replace("{{prev_page}}", $prevPage, $creaOpera);
$creaOpera = str_replace("{{page_title}}", $pageTitle, $creaOpera);
$creaOpera = str_replace("{{keep_main_image}}", $keepMainImage, $creaOpera);
$creaOpera = str_replace("{{keep_additional_images}}", $keepAdditionalImages, $creaOpera);
$creaOpera = str_replace("{{main_image}}", $mainImageContainer, $creaOpera);
$creaOpera = str_replace("{{additional_images}}", $additionalImagesContainer, $creaOpera);
$creaOpera = str_replace("{{title}}", $title, $creaOpera);
$creaOpera = str_replace("{{description}}", $description, $creaOpera);
$creaOpera = str_replace("{{labels}}", $labelsContainer, $creaOpera);
$creaOpera = str_replace("{{start_date}}", $startDate, $creaOpera);
$creaOpera = str_replace("{{end_date}}", $endDate, $creaOpera);
$creaOpera = str_replace("{{height}}", $height, $creaOpera);
$creaOpera = str_replace("{{width}}", $width, $creaOpera);
$creaOpera = str_replace("{{depth}}", $depth, $creaOpera);
$creaOpera = str_replace("{{submit_button}}", $submitButton, $creaOpera);
$creaOpera = str_replace("{{error_message}}", $errorMessage, $creaOpera);
echo ($creaOpera);

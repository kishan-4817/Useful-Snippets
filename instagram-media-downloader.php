<?php
/**
 * Handles the shortcode for the Instagram media downloader.
 *
 * @since 1.0.0
 *
 * @return string The HTML for the shortcode.
 */
function instagram_media_downloader_shortcode() {
    if (isset($_POST['instagram_url'])) {
        $instagram_url = sanitize_text_field($_POST['instagram_url']);

        if (strpos($instagram_url, '/stories/') !== false) {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://instagram-stories-downloader1.p.rapidapi.com/story.php?url=" . urlencode($instagram_url),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "x-rapidapi-host: instagram-stories-downloader1.p.rapidapi.com",
                    "x-rapidapi-key: ee9b5c42bamsh0b3a9e2e9c9b0f5p1e6b8fjsnfe4c5f7b7a"
                ],
            ]);
        } else {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://instagram-media-downloader9.p.rapidapi.com/post.php?url=" . urlencode($instagram_url),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "x-rapidapi-host: instagram-media-downloader9.p.rapidapi.com",
                    "x-rapidapi-key: EXAMPLE API"
                ],
            ]);
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $result = "cURL Error #:" . $err;
        } else {
            $data = json_decode($response, true);

            echo "<pre>";
            print_r($data);
            echo "</pre>";

            // Traverse the nested arrays to find the media URL
            if (isset($data['items'][0]['video_versions'])) {
                $video_versions = $data['items'][0]['video_versions'];
                $best_quality_video = $video_versions[0]['url']; // Selecting the first version, which is typically the best quality
                $result = "<h3>Download Video:</h3>";
                $result .= "<a href='" . esc_url($best_quality_video) . "' download>Click here to download the video</a>";
            } elseif (isset($data['items'][0]['image_versions2']['candidates'][0]['url'])) {
                $image_url = $data['items'][0]['image_versions2']['candidates'][0]['url'];
                $result = "<h3>Download Image:</h3>";
                $result .= "<a href='" . esc_url($image_url) . "' download>Click here to download the image</a>";
            } else {
                $result = "No media found or invalid Instagram URL.";
            }
        }
    }

    ob_start();
    ?>
    <form method="post">
        <label for="instagram_url">Enter Instagram Post URL:</label>
        <input type="text" id="instagram_url" name="instagram_url" value="<?php echo isset($instagram_url) ? esc_attr($instagram_url) : ''; ?>" required>
        <input type="submit" value="Download">
    </form>

    <?php if (isset($result)): ?>
        <div>
            <?php echo $result; ?>
        </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}

add_shortcode('instagram_downloader', 'instagram_media_downloader_shortcode');

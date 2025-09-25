<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/png" href="favicon1.png" />
  <meta charset="UTF-8">
  <title>Consultation des emplacements</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background-color: #f5f8ff;
    }
    main {
      display: flex;
      gap: 30px;
      padding: 2rem;
      flex-wrap: wrap;
      justify-content: center;
    }
    /* Grille */
    .grille {
      display: grid;
      grid-template-columns: repeat(3, 80px);
      grid-gap: 10px;
    }
    .case {
      width: 80px;
      height: 80px;
      background: #ddd;
      border: 2px solid #444;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.3s;
      font-weight: bold;
    }
    .case:hover { background: #aaa; }

    /* Zone aperçu */
    .consultation {
      flex: 1;
      border: 2px solid #444;
      border-radius: 8px;
      padding: 10px;
      text-align: center;
    }
    .consultation img {
      max-width: 100%;
      max-height: 400px;
      display: none;
      margin-top: 10px;
    }
  </style>
</head>
<body>
<?php $baseUrl = '..'; require __DIR__ . '/../partials/top_nav.php'; ?>

<main>
  <!-- Grille -->
  <div class="grille">
    <div class="case" data-img="A1.png">A1</div>
    <div class="case" data-img="A2.png">A2</div>
    <div class="case" data-img="A3.png">A3</div>
    <div class="case" data-img="B1.png">B1</div>
    <div class="case" data-img="B2.png">B2</div>
    <div class="case" data-img="B3.png">B3</div>
    <div class="case" data-img="C1.png">C1</div>
    <div class="case" data-img="C2.png">C2</div>
    <div class="case" data-img="C3.png">C3</div>
    <div class="case" data-img="D1.png">D1</div>
    <div class="case" data-img="D2.png">D2</div>
    <div class="case" data-img="D3.png">D3</div>

    <div class="case" data-img="AA1.png">AA1</div>
    <div class="case" data-img="AA2.png">AA2</div>
    <div class="case" data-img="AA3.png">AA3</div>
    <div class="case" data-img="BB1.png">BB1</div>
    <div class="case" data-img="BB2.png">BB2</div>
    <div class="case" data-img="BB3.png">BB3</div>
    <div class="case" data-img="CC1.png">CC1</div>
    <div class="case" data-img="CC2.png">CC2</div>
    <div class="case" data-img="CC3.png">CC3</div>
    <div class="case" data-img="DD1.png">DD1</div>
    <div class="case" data-img="DD2.png">DD2</div>
    <div class="case" data-img="DD3.png">DD3</div>
  </div>

  <!-- Zone de consultation -->
  <div class="consultation">
    <h3>Vue 3D de l’emplacement</h3>
    <img id="vue" src="" alt="Aperçu 3D">
  </div>

</main>

  <script>
    const cases = document.querySelectorAll(".case");
    const vue = document.getElementById("vue");

    cases.forEach(c => {
      c.addEventListener("click", () => {
        vue.src = c.dataset.img;
        vue.style.display = "block";
      });
    });
  </script>

</body>
</html>

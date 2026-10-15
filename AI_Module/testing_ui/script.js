const imageInput = document.getElementById("image");
const preview = document.getElementById("preview");
const analyzeBtn = document.getElementById("analyzeBtn");
const output = document.getElementById("output");

imageInput.addEventListener("change", () => {

    const file = imageInput.files[0];

    if (!file) {
        preview.style.display = "none";
        return;
    }

    preview.src = URL.createObjectURL(file);
    preview.style.display = "block";

});

analyzeBtn.addEventListener("click", async () => {

    const description = document.getElementById("description").value.trim();
    const image = imageInput.files[0];

    if (!description) {
        alert("Please enter description.");
        return;
    }

    if (!image) {
        alert("Please select an image.");
        return;
    }

    const formData = new FormData();

    formData.append("description", description);
    formData.append("image", image);

    output.textContent = "Analyzing...";

    try {

        const response = await fetch("http://127.0.0.1:5000/new-report", {
            method: "POST",
            body: formData
        });

        console.log("Status:", response.status);
        console.log("Content-Type:", response.headers.get("content-type"));

        const text = await response.text();

        console.log(text);

        output.textContent = text;

    }

    catch (err) {

        console.error(err);

        output.textContent =
`ERROR

${err.name}

${err.message}`;

    }

});
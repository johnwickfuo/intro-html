class SnakeGame {
    constructor() {
        this.canvas = document.getElementById('gameCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.scoreElement = document.getElementById('score');
        this.highScoreElement = document.getElementById('high-score');
        this.gameOverElement = document.getElementById('gameOver');
        this.finalScoreElement = document.getElementById('finalScore');
        
        // Game settings
        this.gridSize = 20;
        this.tileCount = this.canvas.width / this.gridSize;
        
        // Game state
        this.snake = [{x: 10, y: 10}];
        this.food = {};
        this.dx = 0;
        this.dy = 0;
        this.score = 0;
        this.gameRunning = false;
        this.gamePaused = false;
        this.gameLoop = null;
        
        // Load high score from localStorage
        this.highScore = localStorage.getItem('snakeHighScore') || 0;
        this.highScoreElement.textContent = this.highScore;
        
        this.setupEventListeners();
        this.generateFood();
        this.draw();
    }
    
    setupEventListeners() {
        // Button controls
        document.getElementById('startBtn').addEventListener('click', () => this.startGame());
        document.getElementById('pauseBtn').addEventListener('click', () => this.togglePause());
        document.getElementById('resetBtn').addEventListener('click', () => this.resetGame());
        document.getElementById('restartBtn').addEventListener('click', () => this.restartGame());
        
        // Keyboard controls
        document.addEventListener('keydown', (e) => this.handleKeyPress(e));
    }
    
    handleKeyPress(e) {
        if (!this.gameRunning || this.gamePaused) return;
        
        switch(e.key.toLowerCase()) {
            case 'arrowup':
            case 'w':
                if (this.dy !== 1) { this.dx = 0; this.dy = -1; }
                break;
            case 'arrowdown':
            case 's':
                if (this.dy !== -1) { this.dx = 0; this.dy = 1; }
                break;
            case 'arrowleft':
            case 'a':
                if (this.dx !== 1) { this.dx = -1; this.dy = 0; }
                break;
            case 'arrowright':
            case 'd':
                if (this.dx !== -1) { this.dx = 1; this.dy = 0; }
                break;
            case ' ':
                e.preventDefault();
                this.togglePause();
                break;
        }
    }
    
    startGame() {
        if (this.gameRunning) return;
        
        this.gameRunning = true;
        this.gamePaused = false;
        this.dx = 1;
        this.dy = 0;
        
        this.gameLoop = setInterval(() => {
            if (!this.gamePaused) {
                this.update();
                this.draw();
            }
        }, 150);
        
        document.getElementById('startBtn').textContent = 'Running...';
        document.getElementById('startBtn').disabled = true;
    }
    
    togglePause() {
        if (!this.gameRunning) return;
        
        this.gamePaused = !this.gamePaused;
        document.getElementById('pauseBtn').textContent = this.gamePaused ? 'Resume' : 'Pause';
    }
    
    resetGame() {
        this.stopGame();
        this.snake = [{x: 10, y: 10}];
        this.dx = 0;
        this.dy = 0;
        this.score = 0;
        this.scoreElement.textContent = '0';
        this.generateFood();
        this.draw();
        this.gameOverElement.style.display = 'none';
    }
    
    restartGame() {
        this.resetGame();
        this.startGame();
    }
    
    stopGame() {
        this.gameRunning = false;
        this.gamePaused = false;
        if (this.gameLoop) {
            clearInterval(this.gameLoop);
            this.gameLoop = null;
        }
        document.getElementById('startBtn').textContent = 'Start Game';
        document.getElementById('startBtn').disabled = false;
        document.getElementById('pauseBtn').textContent = 'Pause';
    }
    
    update() {
        const head = {x: this.snake[0].x + this.dx, y: this.snake[0].y + this.dy};
        
        // Check wall collision
        if (head.x < 0 || head.x >= this.tileCount || head.y < 0 || head.y >= this.tileCount) {
            this.gameOver();
            return;
        }
        
        // Check self collision
        for (let segment of this.snake) {
            if (head.x === segment.x && head.y === segment.y) {
                this.gameOver();
                return;
            }
        }
        
        this.snake.unshift(head);
        
        // Check food collision
        if (head.x === this.food.x && head.y === this.food.y) {
            this.score += 10;
            this.scoreElement.textContent = this.score;
            this.generateFood();
            
            // Update high score
            if (this.score > this.highScore) {
                this.highScore = this.score;
                this.highScoreElement.textContent = this.highScore;
                localStorage.setItem('snakeHighScore', this.highScore);
            }
        } else {
            this.snake.pop();
        }
    }
    
    generateFood() {
        this.food = {
            x: Math.floor(Math.random() * this.tileCount),
            y: Math.floor(Math.random() * this.tileCount)
        };
        
        // Make sure food doesn't spawn on snake
        for (let segment of this.snake) {
            if (segment.x === this.food.x && segment.y === this.food.y) {
                this.generateFood();
                return;
            }
        }
    }
    
    draw() {
        // Clear canvas with gradient background
        const gradient = this.ctx.createLinearGradient(0, 0, this.canvas.width, this.canvas.height);
        gradient.addColorStop(0, '#34495e');
        gradient.addColorStop(1, '#2c3e50');
        this.ctx.fillStyle = gradient;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw grid
        this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
        this.ctx.lineWidth = 1;
        for (let i = 0; i < this.tileCount; i++) {
            this.ctx.beginPath();
            this.ctx.moveTo(i * this.gridSize, 0);
            this.ctx.lineTo(i * this.gridSize, this.canvas.height);
            this.ctx.stroke();
            
            this.ctx.beginPath();
            this.ctx.moveTo(0, i * this.gridSize);
            this.ctx.lineTo(this.canvas.width, i * this.gridSize);
            this.ctx.stroke();
        }
        
        // Draw snake
        this.ctx.fillStyle = '#2ecc71';
        this.snake.forEach((segment, index) => {
            if (index === 0) {
                // Snake head - make it slightly different
                this.ctx.fillStyle = '#27ae60';
                this.drawRoundedRect(
                    segment.x * this.gridSize + 2,
                    segment.y * this.gridSize + 2,
                    this.gridSize - 4,
                    this.gridSize - 4,
                    6
                );
                
                // Draw eyes
                this.ctx.fillStyle = 'white';
                this.ctx.fillRect(segment.x * this.gridSize + 6, segment.y * this.gridSize + 6, 3, 3);
                this.ctx.fillRect(segment.x * this.gridSize + 11, segment.y * this.gridSize + 6, 3, 3);
            } else {
                // Snake body
                this.ctx.fillStyle = '#2ecc71';
                this.drawRoundedRect(
                    segment.x * this.gridSize + 2,
                    segment.y * this.gridSize + 2,
                    this.gridSize - 4,
                    this.gridSize - 4,
                    4
                );
            }
        });
        
        // Draw food
        this.ctx.fillStyle = '#e74c3c';
        this.drawRoundedRect(
            this.food.x * this.gridSize + 2,
            this.food.y * this.gridSize + 2,
            this.gridSize - 4,
            this.gridSize - 4,
            8
        );
        
        // Add a shine effect to food
        this.ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
        this.ctx.fillRect(
            this.food.x * this.gridSize + 4,
            this.food.y * this.gridSize + 4,
            6,
            6
        );
    }
    
    drawRoundedRect(x, y, width, height, radius) {
        this.ctx.beginPath();
        this.ctx.roundRect(x, y, width, height, radius);
        this.ctx.fill();
    }
    
    gameOver() {
        this.stopGame();
        this.finalScoreElement.textContent = this.score;
        this.gameOverElement.style.display = 'block';
    }
}

// Initialize game when page loads
window.addEventListener('load', () => {
    new SnakeGame();
});

// Add touch controls for mobile
let touchStartX = 0;
let touchStartY = 0;

document.addEventListener('touchstart', (e) => {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
});

document.addEventListener('touchend', (e) => {
    if (!touchStartX || !touchStartY) return;
    
    const touchEndX = e.changedTouches[0].clientX;
    const touchEndY = e.changedTouches[0].clientY;
    
    const diffX = touchStartX - touchEndX;
    const diffY = touchStartY - touchEndY;
    
    if (Math.abs(diffX) > Math.abs(diffY)) {
        // Horizontal swipe
        if (diffX > 0) {
            // Swipe left
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowLeft'}));
        } else {
            // Swipe right
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowRight'}));
        }
    } else {
        // Vertical swipe
        if (diffY > 0) {
            // Swipe up
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowUp'}));
        } else {
            // Swipe down
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowDown'}));
        }
    }
    
    touchStartX = 0;
    touchStartY = 0;
});
// 主JavaScript文件

// 复制文本到剪贴板功能
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    
    if (!element) {
        alert('找不到要复制的内容');
        return;
    }
    
    // 选择文本内容
    element.select();
    element.setSelectionRange(0, 99999); // 兼容移动设备
    
    try {
        // 执行复制操作
        document.execCommand('copy');
        
        // 创建复制成功提示
        showCopySuccess(element);
    } catch (err) {
        alert('复制失败，请手动选择复制');
        console.error('复制失败:', err);
    }
    
    // 取消选择
    window.getSelection().removeAllRanges();
}

// 显示复制成功提示
function showCopySuccess(element) {
    // 创建提示元素
    const tooltip = document.createElement('div');
    tooltip.className = 'absolute bg-green-600 text-white py-1 px-3 rounded-md text-sm z-50';
    tooltip.textContent = '已复制到剪贴板';
    tooltip.style.position = 'absolute';
    tooltip.style.fontFamily = 'inherit';
    tooltip.style.pointerEvents = 'none';
    tooltip.style.opacity = '0';
    tooltip.style.transition = 'opacity 0.3s ease';
    
    // 获取元素位置
    const rect = element.getBoundingClientRect();
    const button = element.nextElementSibling; // 获取复制按钮
    const buttonRect = button ? button.getBoundingClientRect() : null;
    
    // 添加到文档
    document.body.appendChild(tooltip);
    
    // 设置位置（在按钮上方居中）
    if (buttonRect) {
        tooltip.style.left = `${buttonRect.left + buttonRect.width / 2}px`;
        tooltip.style.top = `${buttonRect.top - 30}px`;
        tooltip.style.transform = 'translateX(-50%)';
    } else {
        tooltip.style.left = `${rect.left + rect.width / 2}px`;
        tooltip.style.top = `${rect.top - 30}px`;
        tooltip.style.transform = 'translateX(-50%)';
    }
    
    // 显示提示
    setTimeout(() => {
        tooltip.style.opacity = '1';
    }, 10);
    
    // 3秒后移除提示
    setTimeout(() => {
        tooltip.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(tooltip);
        }, 300);
    }, 2000);
}

// 表单验证和数据提取
function validateForm() {
    const textarea = document.getElementById('callback_data_list');
    const originalText = textarea.value.trim();
    const lines = originalText.split('\n');
    
    if (lines.length === 0 || (lines.length === 1 && lines[0].trim() === '')) {
        alert('请输入账号数据');
        return false;
    }
    
    // 提取有效的access_token和openid信息
    const extractedLines = [];
    let hasValidData = false;
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;
        
        // 检查是否是直接的access_token=xxx&openid=yyy格式
        const directPattern = /^access_token=[^&]+&openid=[^&]+$/;
        if (directPattern.test(line)) {
            extractedLines.push(line);
            hasValidData = true;
            continue;
        }
        
        // 从复杂格式中提取access_token和openid信息
        // 匹配#access_token=xxx&openid=yyy或access_token=xxx&openid=yyy格式
        const extractPattern = /[#]?(access_token=[^&]+&.*?openid=[^&]+)/i;
        const match = line.match(extractPattern);
        
        if (match && match[1]) {
            // 提取出access_token和openid部分
            const tokenPart = match[1];
            // 确保包含access_token和openid
            if (tokenPart.includes('access_token=') && tokenPart.includes('openid=')) {
                extractedLines.push(tokenPart);
                hasValidData = true;
                continue;
            }
        }
        
        // 如果没有匹配到有效格式，跳过此行但不报错
    }
    
    if (!hasValidData) {
        alert('没有找到有效的access_token和openid信息');
        return false;
    }
    
    // 将提取后的信息重新设置到textarea中，以便提交
    textarea.value = extractedLines.join('\n');
    
    return true;
}

// 页面加载完成后执行
window.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('queryForm');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!validateForm()) {
                event.preventDefault();
            }
        });
    }
    
    // 自动调整textarea高度
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });
});